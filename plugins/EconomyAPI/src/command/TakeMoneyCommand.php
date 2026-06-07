<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\command;

use alvin0319\AwaitCommand\AwaitCommand;
use alvin0319\EconomyAPI\EconomyAPI;
use alvin0319\EconomyAPI\registry\CurrencyRegistry;
use alvin0319\EconomyAPI\session\EconomySession;
use alvin0319\EconomyAPI\transaction\Transaction;
use alvin0319\EconomyAPI\transaction\TransactionResult;
use alvin0319\EconomyAPI\transaction\TransactionType;
use alvin0319\EconomyAPI\utils\CurrencyFormatter;
use alvin0319\SessionManager\SessionHandle;
use alvin0319\SessionManager\SessionLifecycleState;
use alvin0319\SessionManager\SessionManager;
use alvin0319\StarGateExtension\MessagePacket;
use alvin0319\StarGateExtension\StarGateExtension;
use alvin0319\XuidCore\XuidCore;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use skh6075\ExtensionPlugin\ExtensionPlugin;
use skh6075\ExtensionPlugin\Message;
use function skh6075\ExtensionPlugin\assumeNotNull;

final class TakeMoneyCommand extends AwaitCommand implements PluginOwned{
	use PluginOwnedTrait;

	public function __construct(
		private readonly CurrencyRegistry $registry
	){
		parent::__construct("돈뺏기", "플레이어의 돈을 뺏습니다.", "/돈설정 <플레이어> <금액> [화폐]");
		$this->setPermission("economyapi.command.take");
		$this->owningPlugin = EconomyAPI::getInstance();
	}

	public function executeAsync(CommandSender $sender, string $commandLabel, array $args) : \Generator{
		if(count($args) < 2){
			Message::warn($sender, "사용법: §f/돈설정 <플레이어> <금액> [화폐]");
			return;
		}
		$name = assumeNotNull(array_shift($args));
		$amountString = assumeNotNull(array_shift($args));
		if(!is_numeric($amountString) || ($amount = (int) $amountString) < 0){
			Message::warn($sender, "금액은 §f0 이상의 숫자§c여야 합니다.");
			return;
		}
		$currency = $this->registry->getDefaultCurrency();
		if(count($args) > 0){
			$currency = $this->registry->getCurrencyOrDefault(assumeNotNull(array_shift($args)));
		}
		/** @phpstan-var SessionHandle<EconomySession>|null $targetHandle */
		$targetHandle = null;
		try{
			$xuid = yield from XuidCore::lookupXuid($name);
			if($xuid === null){
				Message::sendTargetNotFounded($sender);
				return;
			}
			$targetHandle = yield from EconomyAPI::getInstance()->createSession($xuid, ExtensionPlugin::findPlayerByXuid($xuid));
			if($targetHandle === null){
				Message::sendTargetNotFounded($sender);
				return;
			}
			$targetSession = $targetHandle->getSession();
			/** @var TransactionResult $result */
			$result = yield from Transaction::builder()
				->player($targetSession)
				->currency($currency)
				->amount($amount)
				->type(TransactionType::DEDUCT)
				->reason("TakeMoneyCommand: {$sender->getName()}")
				->build()
				->execute();
			if($result !== TransactionResult::SUCCESS){
				Message::alert($sender, "돈 차감에 실패했습니다: " . $result->value);
			}else{
				Message::success($sender, "§f{$name}§a님의 재화를 §f" . CurrencyFormatter::kindly($amount, $currency) . "§r§a 만큼 차감했습니다.");
				StarGateExtension::broadcastPacket(MessagePacket::chat(
					content: Message::stringify("§f{$sender->getName()}§7님이 당신의 재화를 §f" . CurrencyFormatter::kindly($amount, $currency) . "§7 만큼 차감했습니다."),
					targetId: $name
				));
			}
		}catch(\Throwable $e){
			Message::sendCommandRootError($this, $sender, $e);
		}finally{
			yield from SessionManager::safelyDisposeSession($targetHandle, $this->owningPlugin);
		}
	}
}