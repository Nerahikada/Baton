<?php

namespace Nerahikada;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\Player;

class Baton extends PluginBase implements Listener{

	public $config;
	public $moderators;
	public $commandHandler;

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		if(!file_exists($this->getDataFolder())) mkdir($this->getDataFolder(), 0744, true);

		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML,
			[
				"pos" => [
					"x" => 0,
					"y" => 0,
					"z" => 0,
					"world" => "world"
				]
			]
		);
		$this->moderators = new Config($this->getDataFolder() . "moderators.txt", Config::ENUM);

		$this->commandHandler = [
			"help" => "onHelpCommand",
			"add" => "onAddCommand",
			"remove" => "onRemoveCommand",
			"set" => "onSetCommand",
			"reload" => "onReloadCommand",
			"give" => "onGiveCommand"
		];
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		if(!$sender->isOp()) return true;
		// 可変関数(コールバック関数)
		$callable = [$this, $this->commandHandler[strtolower(array_shift($args))] ?? $this->commandHandler["help"]];
		call_user_func($callable, $sender, $args);
		return true;
	}


	public function onHelpCommand(CommandSender $sender, array $args){
		$message = "/baton コマンドヘルプ\n§2/baton help §fヘルプを表示します\n§2/baton add <name> §f<name>が警棒を使えるようにします\n§2/baton remove <name> §f<name>が警棒を使えなくなります\n§2/baton set §f現在立っている位置が牢屋に設定されます\n§2/baton reload §f設定ファイルを再読み込みします\n§2/baton give §f警棒を付与します";
		$sender->sendMessage($message);
	}

	public function onAddCommand(CommandSender $sender, array $args){
		$name = strtolower(implode(" ", $args));
		$this->moderators->set($name);
		$this->moderators->save(true);

		$sender->sendMessage($name."が警棒を使えるようになりました");
	}

	public function onRemoveCommand(CommandSender $sender, array $args){
		$name = strtolower(implode(" ", $args));
		$this->moderators->remove($name);
		$this->moderators->save();

		$sender->sendMessage($name."の警棒使用権限を剥奪しました");
	}

	public function onSetCommand(CommandSender $sender, array $args){
		if(!$sender instanceof Player){
			$sender->sendMessage("§cゲーム内で実行してください");
			return;
		}

		$pos = $sender->round(1);
		$this->config->set("pos",
			[
				"x" => $pos->x,
				"y" => $pos->y,
				"z" => $pos->z,
				"world" => $sender->getLevel()->getName()
			]
		);
		$this->config->save();

		$sender->sendMessage("現在の座標を牢屋に設定しました");
	}

	public function onReloadCommand(CommandSender $sender, array $args){
		$this->config->reload();
		$this->moderators->reload();

		$sender->sendMessage("リロードしました");
	}

	public function onGiveCommand(CommandSender $sender, array $args){
		if(!$sender instanceof Player){
			$sender->sendMessage("§cゲーム内で実行してください");
			return;
		}

		$item = Item::get(Item::STICK);
		$item->setCustomName("警棒");
		$item->addEnchantment(Enchantment::getEnchantment(Enchantment::PROTECTION));
		$sender->getInventory()->addItem($item);
	}


	public function onDamage(EntityDamageEvent $event){
		if($event->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK){
			$damager = $event->getDamager();
			$player = $event->getEntity();
			$item = $damager->getInventory()->getItemInHand();
			$a = $damager instanceof Player;
			$b = $player instanceof Player;
			$c = $item->getId() === Item::STICK;
			$d = $item->getCustomName() === "警棒";
			$e = $damager->isOp() || $this->moderators->exists(strtolower($damager->getName()));
			if($a && $b && $c && $d && $e){
				$pos = $this->config->get("pos");
				$pos = new Position($pos["x"], $pos["y"] + 0.1, $pos["z"], $this->getServer()->getLevelByName($pos["world"]));
				$player->teleport($pos);
				$event->setCancelled();
			}
		}
	}

}
