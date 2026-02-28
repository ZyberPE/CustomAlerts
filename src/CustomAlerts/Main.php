<?php

declare(strict_types=1);

namespace CustomAlerts;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class Main extends PluginBase implements Listener {

    private Config $config;

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /* -------------------- MOTD (API 5 SAFE) -------------------- */

    public function onQueryRegenerate(QueryRegenerateEvent $event): void {

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

        $event->getQueryInfo()->setServerName($line1 . "§r\n" . $line2);
        $event->getQueryInfo()->setPlayerCount(count($this->getServer()->getOnlinePlayers()));
        $event->getQueryInfo()->setMaxPlayerCount($this->getServer()->getMaxPlayers());
    }

    /* -------------------- LOGIN CHECKS -------------------- */

    public function onPreLogin(PlayerPreLoginEvent $event): void {

        $protocol = $event->getPlayerInfo()->getProtocolId();
        $serverProtocol = ProtocolInfo::CURRENT_PROTOCOL;

        if($protocol < $serverProtocol && $this->config->getNested("outdated.client.enabled", true)){
            $event->setKickMessage($this->config->getNested("outdated.client.message"));
            $event->cancel();
            return;
        }

        if($protocol > $serverProtocol && $this->config->getNested("outdated.server.enabled", true)){
            $event->setKickMessage($this->config->getNested("outdated.server.message"));
            $event->cancel();
            return;
        }

        if($this->getServer()->hasWhitelist()
            && !$event->getPlayerInfo()->isWhitelisted()
            && $this->config->getNested("whitelist.enabled", true)){
            $event->setKickMessage($this->config->getNested("whitelist.message"));
            $event->cancel();
            return;
        }

        if(count($this->getServer()->getOnlinePlayers()) >= $this->getServer()->getMaxPlayers()
            && $this->config->getNested("full-server.enabled", true)){
            $event->setKickMessage($this->config->getNested("full-server.message"));
            $event->cancel();
        }
    }

    /* -------------------- JOIN / QUIT -------------------- */

    public function onJoin(PlayerJoinEvent $event): void {

        if(!$this->config->getNested("join.enabled", true)) return;

        $message = str_replace("{PLAYER}", $event->getPlayer()->getName(),
            $this->config->getNested("join.message"));

        foreach($this->getServer()->getOnlinePlayers() as $player){
            if($player->hasPermission("customalerts.join")){
                $player->sendMessage($message);
            }
        }

        $event->setJoinMessage("");
    }

    public function onQuit(PlayerQuitEvent $event): void {

        if(!$this->config->getNested("quit.enabled", true)) return;

        $message = str_replace("{PLAYER}", $event->getPlayer()->getName(),
            $this->config->getNested("quit.message"));

        foreach($this->getServer()->getOnlinePlayers() as $player){
            if($player->hasPermission("customalerts.quit")){
                $player->sendMessage($message);
            }
        }

        $event->setQuitMessage("");
    }

    /* -------------------- DEATH SYSTEM -------------------- */

    public function onDeath(EntityDeathEvent $event): void {

        $entity = $event->getEntity();
        if(!$entity instanceof Player) return;

        $cause = $entity->getLastDamageCause();
        if(!$cause instanceof EntityDamageEvent) return;

        $playerName = $entity->getName();
        $path = "death.default";
        $permission = "customalerts.death.default";

        switch($cause->getCause()){

            case EntityDamageEvent::CAUSE_CONTACT:
                $path = "death.contact";
                $permission = "customalerts.death.contact";
            break;

            case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                $path = "death.kill";
                $permission = "customalerts.death.kill";
            break;

            case EntityDamageEvent::CAUSE_PROJECTILE:
                $path = "death.projectile";
                $permission = "customalerts.death.projectile";
            break;

            case EntityDamageEvent::CAUSE_SUFFOCATION:
                $path = "death.suffocation";
                $permission = "customalerts.death.suffocation";
            break;

            case EntityDamageEvent::CAUSE_FALL:
                $path = "death.fall";
                $permission = "customalerts.death.fall";
            break;

            case EntityDamageEvent::CAUSE_FIRE:
                $path = "death.fire";
                $permission = "customalerts.death.fire";
            break;

            case EntityDamageEvent::CAUSE_FIRE_TICK:
                $path = "death.onfire";
                $permission = "customalerts.death.onfire";
            break;

            case EntityDamageEvent::CAUSE_LAVA:
                $path = "death.lava";
                $permission = "customalerts.death.lava";
            break;

            case EntityDamageEvent::CAUSE_DROWNING:
                $path = "death.drowning";
                $permission = "customalerts.death.drowning";
            break;

            case EntityDamageEvent::CAUSE_BLOCK_EXPLOSION:
            case EntityDamageEvent::CAUSE_ENTITY_EXPLOSION:
                $path = "death.explosion";
                $permission = "customalerts.death.explosion";
            break;

            case EntityDamageEvent::CAUSE_VOID:
                $path = "death.void";
                $permission = "customalerts.death.void";
            break;

            case EntityDamageEvent::CAUSE_SUICIDE:
                $path = "death.suicide";
                $permission = "customalerts.death.suicide";
            break;

            case EntityDamageEvent::CAUSE_MAGIC:
                $path = "death.magic";
                $permission = "customalerts.death.magic";
            break;
        }

        if(!$this->config->getNested("$path.enabled", true)) return;

        $message = str_replace("{PLAYER}", $playerName,
            $this->config->getNested("$path.message"));

        foreach($this->getServer()->getOnlinePlayers() as $player){
            if($player->hasPermission($permission)){
                $player->sendMessage($message);
            }
        }

        $event->setDeathMessage("");
    }
}
