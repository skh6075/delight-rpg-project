<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\command;

use alvin0319\EconomyAPI\EconomyAPI;
use alvin0319\EconomyAPI\utils\CurrencyFormatter;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use skh6075\ExtensionPlugin\Message;

final class MyMoneyCommand extends Command implements PluginOwned{
	use PluginOwnedTrait;

	public function __construct(){
		parent::__construct("내돈", "내 잔액을 확인합니다.", "/내돈");
		$this->setPermission("economyapi.command.mymoney");
		$this->setAliases(["money", "mymoney"]);
		$this->owningPlugin = EconomyAPI::getInstance();
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void{
		if(!$sender instanceof Player){
			Message::sendPlayerOnlyMessage($sender);
			return;
		}
		$session = EconomyAPI::getInstance()->getSession($sender);
		if($session === null){
			Message::sendSessionNotLoaded($sender);
			return;
		}
		Message::log($sender, "내 잔액을 확인합니다.");
		foreach($session->getBalances() as $balance){
			Message::info($sender, "§f" . $balance->getCurrency()->name . ": " . CurrencyFormatter::kindly($balance->getAmount(), $balance->getCurrency()));
		}
	}
}