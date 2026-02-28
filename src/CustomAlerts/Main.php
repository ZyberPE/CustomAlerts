<?php

declare(strict_types=1);

namespace CustomAlerts;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\scheduler\ClosureTask;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\Server;

class Main extends PluginBase implements Listener {

    private Config $config;

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveResource("config.yml");

        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->updateMotd();

        $this->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(fn() => $this->updateMotd()),
            20 * (int)$this->config->getNested("motd.auto-update-seconds", 60)
        );
    }

    /* -------------------- MOTD -------------------- */

    private function updateMotd(): void {

        if(!$this->config->getNested("motd.enabled", true)) return;

        $replace = [
            "{TIME}" => date("H:i:s"),
            "{ONLINE}" => (string)count($this->getServer()->getOnlinePlayers()),
            "{MAX}" => (string)$this->getServer()->getMaxPlayers()
        ];

        $line1 = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->config->getNested("motd.line1")
        );

        $line2 = str_replace(
            array_keys($replace),
            array_values($replace),
            $this->config->getNested("motd.line2")
        );

        $this->getServer()->setMotd($line1 . "§r\n" . $line2);
    }

    /* -------------------- LOGIN CHECKS -------------------- */

    public function onPreLogin(PlayerPreLoginEvent $event): void {

        $protocol = $event->getPlayerInfo()->getProtocolId();
        $serverProtocol = ProtocolInfo::CURRENT_PROTOCOL;

        // Outdated Client
        if($protocol < $serverProtocol && $this->config->getNested("outdated.client.enabled", true)){
            $event->setKickMessage($this->config->getNested("outdated.client.message"));
            $event->cancel();
            return;
        }

        // Outdated Server
        if($protocol > $serverProtocol && $this->config->getNested("outdated.server.enabled", true)){
            $event->setKickMessage($this->config->getNested("outdated.server.message"));
            $event->cancel();
            return;
        }

        // Whitelist
        if($this->getServer()->hasWhitelist()
            && !$event->getPlayerInfo()->isWhitelisted()
            && $this->config->getNested("whitelist.enabled", true)){
            $event->setKickMessage($this->config->getNested("whitelist.message"));
            $event->cancel();
            return;
        }

        // Full Server
        if(count($this->getServer()->getOnlinePlayers()) >= $this->getServer()->getMaxPlayers()
            && $this->config->getNested("full-server.enabled", true)){
            $event->setKickMessage($this->config->getNested("full-server.message"));
            $event->cancel();
        }
    }

    /* -------------------- JOIN / QUIT -------------------- */

    public function onJoin(PlayerJoinEvent $event): void {
        if(!$this->config->getNested("join.enabled", true)) return;

        $msg = str_replace("{PLAYER}", $event->getPlayer()->getName(),
            $this->config->getNested("join.message"));

        $event->setJoinMessage($msg);
    }

    public function onQuit(PlayerQuitEvent $event): void {
        if(!$this->config->getNested("quit.enabled", true)) return;

        $msg = str_replace("{PLAYER}", $event->getPlayer()->getName(),
            $this->config->getNested("quit.message"));

        $event->setQuitMessage($msg);
    }

    /* -------------------- DEATH SYSTEM -------------------- */

    public function onDeath(EntityDeathEvent $event): void {

        $entity = $event->getEntity();
        if(!$entity instanceof Player) return;

        $cause = $entity->getLastDamageCause();
        if(!$cause instanceof EntityDamageEvent) return;

        $player = $entity->getName();
        $path = "death.default";
        $replace = ["{PLAYER}" => $player];

        switch($cause->getCause()){

            case EntityDamageEvent::CAUSE_CONTACT:
                $path = "death.contact";
                if($cause instanceof EntityDamageByBlockEvent){
                    $replace["{BLOCK}"] = $cause->getDamager()?->getName() ?? "block";
                }
            break;

            case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                $path = "death.kill";
                if($cause instanceof EntityDamageByEntityEvent){
                    $replace["{KILLER}"] = $cause->getDamager()->getName();
                }
            break;

            case EntityDamageEvent::CAUSE_PROJECTILE:
                $path = "death.projectile";
            break;

            case EntityDamageEvent::CAUSE_SUFFOCATION: $path = "death.suffocation"; break;
            case EntityDamageEvent::CAUSE_FALL: $path = "death.fall"; break;
            case EntityDamageEvent::CAUSE_FIRE: $path = "death.fire"; break;
            case EntityDamageEvent::CAUSE_FIRE_TICK: $path = "death.onfire"; break;
            case EntityDamageEvent::CAUSE_LAVA: $path = "death.lava"; break;
            case EntityDamageEvent::CAUSE_DROWNING: $path = "death.drowning"; break;

            case EntityDamageEvent::CAUSE_BLOCK_EXPLOSION:
            case EntityDamageEvent::CAUSE_ENTITY_EXPLOSION:
                $path = "death.explosion";
            break;

            case EntityDamageEvent::CAUSE_VOID: $path = "death.void"; break;
            case EntityDamageEvent::CAUSE_SUICIDE: $path = "death.suicide"; break;
            case EntityDamageEvent::CAUSE_MAGIC: $path = "death.magic"; break;
        }

        if(!$this->config->getNested("$path.enabled", true)) return;

        $message = $this->config->getNested("$path.message");

        $event->setDeathMessage(str_replace(
            array_keys($replace),
            array_values($replace),
            $message
        ));
    }
}
