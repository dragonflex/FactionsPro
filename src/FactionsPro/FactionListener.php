<?php
namespace FactionsPro;

use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerDeathEvent;

class FactionListener implements Listener {
	/** @var FactionMain $plugin */
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}
	
	public function factionChat(PlayerChatEvent $PCE) {
		
		$player = $PCE->getPlayer()->getName();
		//MOTD Check

		if($this->plugin->motdWaiting($player)) {
			if(time() - $this->plugin->getMOTDTime($player) > 30) {
				$PCE->getPlayer()->sendMessage($this->plugin->formatMessage("Timed out. Please use /f desc again."));
				$this->plugin->db->query("DELETE FROM motdrcv WHERE player='$player';");
				$PCE->setCancelled(true);
				return;
			} else {
				$motd = $PCE->getMessage();
				$faction = $this->plugin->getPlayerFaction($player);
				$this->plugin->setMOTD($faction, $player, $motd);
				$PCE->setCancelled(true);
				$PCE->getPlayer()->sendMessage($this->plugin->formatMessage("Successfully updated the faction description. Type /f info.", true));
			}
			return;
		}
		if(isset($this->plugin->factionChatActive[$player])) {
			if($this->plugin->factionChatActive[$player]) {
				$msg = $PCE->getMessage();
				$faction = $this->plugin->getPlayerFaction($player);
				foreach($this->plugin->getServer()->getOnlinePlayers() as $fP) {
					if($this->plugin->getPlayerFaction($fP->getName()) == $faction) {
						if($this->plugin->getServer()->getPlayer($fP->getName())) {
							$PCE->setCancelled(true);
							$this->plugin->getServer()->getPlayer($fP->getName())->sendMessage(TextFormat::DARK_GREEN."[$faction]".TextFormat::BLUE." $player: ".TextFormat::AQUA. $msg);
						}
					}
				}
			}
		}
		if(isset($this->plugin->allyChatActive[$player])) {
			if($this->plugin->allyChatActive[$player]) {
				$msg = $PCE->getMessage();
				$faction = $this->plugin->getPlayerFaction($player);
				foreach($this->plugin->getServer()->getOnlinePlayers() as $fP) {
					if($this->plugin->areAllies($this->plugin->getPlayerFaction($fP->getName()), $faction)) {
						if($this->plugin->getServer()->getPlayer($fP->getName())) {
							$PCE->setCancelled(true);
							$this->plugin->getServer()->getPlayer($fP->getName())->sendMessage(TextFormat::DARK_GREEN."[$faction]".TextFormat::BLUE." $player: ".TextFormat::AQUA. $msg);
							$PCE->getPlayer()->sendMessage(TextFormat::DARK_GREEN."[$faction]".TextFormat::BLUE." $player: ".TextFormat::AQUA. $msg);
						}
					}
				}
			}
		}
	}
	
	public function factionPVP(EntityDamageEvent $factionDamage) {
		if($factionDamage instanceof EntityDamageByEntityEvent) {
			$damaged = $factionDamage->getEntity();
			$damager = $factionDamage->getDamager();
			if($damaged instanceof Player and $damager instanceof Player) {
				if(!$this->plugin->isInFaction($damaged->getName()) or !$this->plugin->isInFaction($damager->getName())) {
					return;
				}
				if(($factionDamage->getEntity() instanceof Player) and ($factionDamage->getDamager() instanceof Player)) {
					$player1 = $damaged->getName();
					$player2 = $damager->getName();
					$f1 = $this->plugin->getPlayerFaction($player1);
					$f2 = $this->plugin->getPlayerFaction($player2);
					if($this->plugin->sameFaction($player1, $player2) == true or $this->plugin->areAllies($f1,$f2)) {
						$factionDamage->setCancelled(true);
					}
				}
			}
		}
	}
	public function factionBlockBreakProtect(BlockBreakEvent $event) {
		if($this->plugin->isInPlot($event->getPlayer())) {
			if($this->plugin->inOwnPlot($event->getPlayer())) {
				return;
			} else {
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage($this->plugin->formatMessage("You cannot break blocks here. This is already a property of a faction. Type /f plotinfo for details."));
				return;
			}
		}
	}
	
	public function factionBlockPlaceProtect(BlockPlaceEvent $event) {
		if($this->plugin->isInPlot($event->getPlayer())) {
			if($this->plugin->inOwnPlot($event->getPlayer())) {
				return;
			} else {
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage($this->plugin->formatMessage("You cannot place blocks here. This is already a property of a faction. Type /f plotinfo for details."));
				return;
			}
		}
	}
	public function onKill(PlayerDeathEvent $event) {
		$ent = $event->getEntity();
		$cause = $event->getEntity()->getLastDamageCause();
		if($cause instanceof EntityDamageByEntityEvent) {
			$killer = $cause->getDamager();
			if($killer instanceof Player) {
				$p = $killer->getPlayer()->getName();
				if($this->plugin->isInFaction($p)) {
					$f = $this->plugin->getPlayerFaction($p);
					$e = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
					if($ent instanceof Player) {
						if($this->plugin->isInFaction($ent->getPlayer()->getName())) {
						   $this->plugin->addFactionPower($f,$e);
						} else {
						   $this->plugin->addFactionPower($f,$e/2);
						}
					}
				}
			}
		}
		if($ent instanceof Player) {
			$e = $ent->getPlayer()->getName();
			if($this->plugin->isInFaction($e)) {
				$f = $this->plugin->getPlayerFaction($e);
				$p = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
				$cause = $ent->getLastDamageCause();
				if($cause instanceof EntityDamageByEntityEvent) {
					$damager = $cause->getDamager();
					if($damager instanceof Player) {
						if($this->plugin->isInFaction($damager->getName())) {
							$this->plugin->subtractFactionPower($f,$p*2);
						} else {
							$this->plugin->subtractFactionPower($f,$p);
						}
					}
				}
			}
		}
	}

	public function onPlayerJoin(PlayerJoinEvent $event) {
		$this->plugin->updateTag($event->getPlayer()->getName());
	}
}