<?php

namespace AdvancedServerMessages;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase implements Listener {

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->startMotdUpdater();
    }

    private function format(string $message, array $extra = []): string {
        $format = $this->getConfig()->get("datetime-format", "H:i:s");

        $replacements = array_merge([
            "{TIME}" => date($format),
            "{TOTALPLAYERS}" => count($this->getServer()->getOnlinePlayers()),
            "{MAXPLAYERS}" => $this->getServer()->getMaxPlayers()
        ], $extra);

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            str_replace("&", "§", $message)
        );
    }

    private function startMotdUpdater(): void {
        $motd = $this->getConfig()->get("Motd");
        if(!$motd["custom"]) return;

        $interval = max(1, (int)$motd["update-timeout"]) * 20;

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $motd = $this->getConfig()->get("Motd");
            $message = $this->format($motd["message"]);
            $this->getServer()->getNetwork()->setName($message);
        }), $interval);
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();

        if(!$player->hasPlayedBefore() && $this->getConfig()->getNested("FirstJoin.enable")){
            $msg = $this->getConfig()->getNested("FirstJoin.message");
            $event->setJoinMessage($this->format($msg, ["{PLAYER}" => $player->getName()]));
            return;
        }

        if($this->getConfig()->getNested("Join.hide")){
            $event->setJoinMessage("");
            return;
        }

        if($this->getConfig()->getNested("Join.custom")){
            $msg = $this->getConfig()->getNested("Join.message");
            $event->setJoinMessage($this->format($msg, ["{PLAYER}" => $player->getName()]));
        }
    }

    public function onQuit(PlayerQuitEvent $event): void {
        if($this->getConfig()->getNested("Quit.hide")){
            $event->setQuitMessage("");
            return;
        }

        if($this->getConfig()->getNested("Quit.custom")){
            $msg = $this->getConfig()->getNested("Quit.message");
            $event->setQuitMessage($this->format($msg, ["{PLAYER}" => $event->getPlayer()->getName()]));
        }
    }

    public function onDeath(EntityDeathEvent $event): void {
        $entity = $event->getEntity();
        if(!$entity instanceof Player) return;

        $cause = $entity->getLastDamageCause();
        if(!$cause instanceof EntityDamageEvent) return;

        if($this->getConfig()->getNested("Death.hide")){
            $event->setDeathMessage("");
            return;
        }

        $playerName = $entity->getName();
        $killerName = "Unknown";
        $blockName = "Unknown";

        if($cause instanceof EntityDamageByEntityEvent){
            $damager = $cause->getDamager();
            if($damager instanceof Player){
                $killerName = $damager->getName();
            }
        }

        if($cause instanceof EntityDamageByBlockEvent){
            $block = $cause->getDamager();
            if($block !== null){
                $blockName = $block->getName();
            }
        }

        $path = "Death.message";

        switch($cause->getCause()){
            case EntityDamageEvent::CAUSE_CONTACT: $path = "Death.death-contact-message.message"; break;
            case EntityDamageEvent::CAUSE_ENTITY_ATTACK: $path = "Death.kill-message.message"; break;
            case EntityDamageEvent::CAUSE_PROJECTILE: $path = "Death.death-projectile-message.message"; break;
            case EntityDamageEvent::CAUSE_SUFFOCATION: $path = "Death.death-suffocation-message.message"; break;
            case EntityDamageEvent::CAUSE_FALL: $path = "Death.death-fall-message.message"; break;
            case EntityDamageEvent::CAUSE_FIRE: $path = "Death.death-fire-message.message"; break;
            case EntityDamageEvent::CAUSE_FIRE_TICK: $path = "Death.death-on-fire-message.message"; break;
            case EntityDamageEvent::CAUSE_LAVA: $path = "Death.death-lava-message.message"; break;
            case EntityDamageEvent::CAUSE_DROWNING: $path = "Death.death-drowning-message.message"; break;
            case EntityDamageEvent::CAUSE_BLOCK_EXPLOSION:
            case EntityDamageEvent::CAUSE_ENTITY_EXPLOSION: $path = "Death.death-explosion-message.message"; break;
            case EntityDamageEvent::CAUSE_VOID: $path = "Death.death-void-message.message"; break;
            case EntityDamageEvent::CAUSE_SUICIDE: $path = "Death.death-suicide-message.message"; break;
            case EntityDamageEvent::CAUSE_MAGIC: $path = "Death.death-magic-message.message"; break;
        }

        if($this->getConfig()->getNested(str_replace(".message", ".custom", $path)) === false) return;

        $msg = $this->getConfig()->getNested($path);

        $formatted = $this->format($msg, [
            "{PLAYER}" => $playerName,
            "{KILLER}" => $killerName,
            "{BLOCK}" => $blockName
        ]);

        $event->setDeathMessage($formatted);
    }
}
