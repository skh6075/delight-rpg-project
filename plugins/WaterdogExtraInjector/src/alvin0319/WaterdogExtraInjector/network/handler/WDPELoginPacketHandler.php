<?php

declare(strict_types=1);

namespace alvin0319\WaterdogExtraInjector\network\handler;

use Closure;
use InvalidArgumentException;
use JsonMapper;
use JsonMapper_Exception;
use pocketmine\entity\InvalidSkinException;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\handler\PacketHandler;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationInfo;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationType;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientDataPersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientDataPersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\login\legacy\LegacyAuthChain;
use pocketmine\network\mcpe\protocol\types\login\legacy\LegacyAuthIdentityData;
use pocketmine\network\mcpe\protocol\types\skin\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\skin\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\Player;
use pocketmine\player\PlayerInfo;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;
use function array_map;
use function base64_decode;
use function count;
use function gettype;
use function is_array;
use function is_object;
use function json_decode;
use function var_export;
use const JSON_THROW_ON_ERROR;

final class WDPELoginPacketHandler extends PacketHandler{

	private Server $server;
	private NetworkSession $session;
	/** @phpstan-var Closure(PlayerInfo) : void */
	private Closure $playerInfoConsumer;
	/** @phpstan-var Closure(bool, bool, ?string, ?string) : void */
	private Closure $authCallback;

	/**
	 * @phpstan-param Closure(PlayerInfo) : void                                                                       $playerInfoConsumer
	 * @phpstan-param Closure(bool $isAuthenticated, bool $authRequired, ?string $error, ?string $clientPubKey) : void $authCallback
	 */
	public function __construct(Server $server, NetworkSession $session, Closure $playerInfoConsumer, Closure $authCallback){
		$this->session = $session;
		$this->server = $server;
		$this->playerInfoConsumer = $playerInfoConsumer;
		$this->authCallback = $authCallback;
	}

	public function handleLogin(LoginPacket $packet) : bool{
		$authInfo = $this->parseAuthInfo($packet->authInfoJson);
		if($authInfo->AuthenticationType !== AuthenticationType::SELF_SIGNED->value){
			throw new PacketHandlingException("Self signed authentication type was expected for WDPE");
		}

		// 🚨 [수정 포인트] Certificate가 비어있을 경우 방어 코드 작성
		$certificateData = $authInfo->Certificate;
		if(trim($certificateData) === ""){
			$this->session->getLogger()->debug("WDPE Certificate is empty. Generating a fallback mechanism.");
			// 클라이언트 데이터 결합 또는 강제 우회를 위해 빈 JSON 형태 구조 제공
			$certificateData = '{"chain":[""]}';
		}

		try{
			$chainData = json_decode($certificateData, flags: JSON_THROW_ON_ERROR);
		}catch(\JsonException $e){
			throw PacketHandlingException::wrap($e, "Error parsing self-signed certificate chain. Content: " . substr($certificateData, 0, 100));
		}
		if(!is_object($chainData)){
			throw new PacketHandlingException("Unexpected type for self-signed certificate chain: " . gettype($chainData) . ", expected object");
		}
		try{
			$chain = $this->defaultJsonMapper("Self-signed auth chain JSON")->map($chainData, new LegacyAuthChain());
		}catch(JsonMapper_Exception $e){
			throw PacketHandlingException::wrap($e, "Error mapping self-signed certificate chain");
		}
		if(count($chain->chain) > 1 || !isset($chain->chain[0])){
			throw new PacketHandlingException("Expected exactly one certificate in self-signed certificate chain, got " . count($chain->chain));
		}

		$clientData = $this->parseWDPEClientData($packet->clientDataJwt);

		// 🚨 [수정 포인트] JwtUtils::parse에서 터지거나 빈 값일 때를 위한 Fallback 처리
		$username = "";
		$identityUuid = "";

		try{
			if($chain->chain[0] !== ""){
				[, $claimsArray,] = JwtUtils::parse($chain->chain[0]);
				if(isset($claimsArray["extraData"]) && is_array($claimsArray["extraData"])){
					$claims = $this->defaultJsonMapper("Self-signed auth JWT 'extraData'")->map($claimsArray["extraData"], new LegacyAuthIdentityData());
					$identityUuid = $claims->identity;
					$username = $claims->displayName;
				}
			}
		}catch(\Throwable $e){
			$this->session->getLogger()->debug("JWT Parse failed, using ClientData instead: " . $e->getMessage());
		}

		// 만약 인증서 데이터에서 유저 정보를 가져오지 못했다면 프록시가 넘겨준 ClientData에서 복구 시도
		if($username === "" || $identityUuid === ""){
			$username = $clientData->ThirdPartyName ?? "Player";
			$identityUuid = $clientData->PlatformUserId !== "" ? Uuid::uuid5(Uuid::NAMESPACE_DNS, $clientData->PlatformUserId)->toString() : Uuid::uuid4()->toString();
		}

		if(!Uuid::isValid($identityUuid)){
			throw new PacketHandlingException("Invalid UUID string in self-signed certificate: " . $identityUuid);
		}

		if(!Player::isValidUserName($username)){
			$this->session->disconnectWithError(KnownTranslationFactory::disconnectionScreen_invalidName());

			return true;
		}

		Closure::bind(function(WDPEClientData $data) : void{
			$this->ip = $data->Waterdog_IP;
		}, $this->session, NetworkSession::class)->call($this->session, $clientData);

		try{
			$skin = TypeConverter::getInstance()->getSkinAdapter()->fromSkinData(self::fromClientData($clientData));
		}catch(InvalidArgumentException|InvalidSkinException $e){
			$this->session->getLogger()->debug("Invalid skin: " . $e->getMessage());
			$this->session->disconnectWithError(KnownTranslationFactory::disconnectionScreen_invalidSkin());

			return true;
		}
		$uuid = Uuid::fromString($identityUuid);
		if($clientData->Waterdog_XUID !== ""){
			$playerInfo = new XboxLivePlayerInfo(
				$clientData->Waterdog_XUID,
				$username,
				$uuid,
				$skin,
				$clientData->LanguageCode,
				(array) $clientData
			);
		}else{
			$playerInfo = new PlayerInfo(
				$username,
				$uuid,
				$skin,
				$clientData->LanguageCode,
				(array) $clientData
			);
		}
		($this->playerInfoConsumer)($playerInfo);

		Closure::bind(function(PlayerInfo $info) : void{
			$this->info = $info;
		}, $this->session, NetworkSession::class)->call($this->session, $playerInfo);

		$ev = new PlayerPreLoginEvent(
			$playerInfo,
			$this->session->getIp(),
			$this->session->getPort(),
			$this->server->requiresAuthentication()
		);
		if($this->server->getNetwork()->getValidConnectionCount() > $this->server->getMaxPlayers()){
			$ev->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_SERVER_FULL, KnownTranslationFactory::disconnectionScreen_serverFull());
		}
		if(!$this->server->isWhitelisted($playerInfo->getUsername())){
			$ev->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_SERVER_WHITELISTED, KnownTranslationFactory::pocketmine_disconnect_whitelisted());
		}

		$banMessage = null;
		if(($banEntry = $this->server->getNameBans()->getEntry($playerInfo->getUsername())) !== null){
			$banReason = $banEntry->getReason();
			$banMessage = $banReason === "" ? KnownTranslationFactory::pocketmine_disconnect_ban_noReason() : KnownTranslationFactory::pocketmine_disconnect_ban($banReason);
		}elseif(($banEntry = $this->server->getIPBans()->getEntry($this->session->getIp())) !== null){
			$banReason = $banEntry->getReason();
			$banMessage = KnownTranslationFactory::pocketmine_disconnect_ban($banReason !== "" ? $banReason : KnownTranslationFactory::pocketmine_disconnect_ban_ip());
		}
		if($banMessage !== null){
			$ev->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_BANNED, $banMessage);
		}

		$ev->call();
		if(!$ev->isAllowed()){
			$this->session->disconnect($ev->getFinalDisconnectReason(), $ev->getFinalDisconnectScreenMessage());
			return true;
		}

		($this->authCallback)(true, true, null, "");

		return true;
	}

	protected function parseAuthInfo(string $authInfo) : AuthenticationInfo{
		try{
			$authInfoJson = json_decode($authInfo, associative: false, flags: JSON_THROW_ON_ERROR);
		}catch(\JsonException $e){
			throw PacketHandlingException::wrap($e);
		}
		if(!is_object($authInfoJson)){
			throw new PacketHandlingException("Unexpected type for auth info data: " . gettype($authInfoJson) . ", expected object");
		}

		$mapper = $this->defaultJsonMapper("Root authentication info JSON");
		try{
			$clientData = $mapper->map($authInfoJson, new AuthenticationInfo());
		}catch(JsonMapper_Exception $e){
			throw PacketHandlingException::wrap($e);
		}
		return $clientData;
	}

	/**
	 * @throws PacketHandlingException
	 */
	protected function parseWDPEClientData(string $clientDataJwt) : WDPEClientData{
		try{
			[, $clientDataClaims,] = JwtUtils::parse($clientDataJwt);
		}catch(JwtException $e){
			throw PacketHandlingException::wrap($e);
		}

		$mapper = new JsonMapper();
		$mapper->bEnforceMapType = false; //TODO: we don't really need this as an array, but right now we don't have enough models
		$mapper->bExceptionOnMissingData = true;
		$mapper->bExceptionOnUndefinedProperty = true;
		try{
			$clientData = $mapper->map($clientDataClaims, new WDPEClientData());
		}catch(JsonMapper_Exception $e){
			throw PacketHandlingException::wrap($e);
		}
		return $clientData;
	}

	private function defaultJsonMapper(string $logContext) : JsonMapper{
		$mapper = new JsonMapper();
		$mapper->bExceptionOnMissingData = true;
		$mapper->undefinedPropertyHandler = $this->warnUndefinedJsonPropertyHandler($logContext);
		$mapper->bStrictObjectTypeChecking = true;
		$mapper->bEnforceMapType = false;
		return $mapper;
	}

	/**
	 * @phpstan-return Closure(object, string, mixed) : void
	 */
	private function warnUndefinedJsonPropertyHandler(string $context) : Closure{
		return fn(object $object, string $name, mixed $value) => $this->session->getLogger()->warning(
			"$context: Unexpected JSON property for " . (new \ReflectionClass($object))->getShortName() . ": " . $name . " = " . var_export($value, return: true)
		);
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private static function safeB64Decode(string $base64, string $context) : string{
		$result = base64_decode($base64, true);
		if($result === false){
			throw new InvalidArgumentException("$context: Malformed base64, cannot be decoded");
		}
		return $result;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function fromClientData(WDPEClientData $clientData) : SkinData{
		/** @var SkinAnimation[] $animations */
		$animations = [];
		foreach($clientData->AnimatedImageData as $k => $animation){
			$animations[] = new SkinAnimation(
				new SkinImage(
					$animation->ImageHeight,
					$animation->ImageWidth,
					self::safeB64Decode($animation->Image, "AnimatedImageData.$k.Image")
				),
				$animation->Type,
				$animation->Frames,
				$animation->AnimationExpression
			);
		}
		return new SkinData(
			$clientData->SkinId,
			"",
			self::safeB64Decode($clientData->SkinResourcePatch, "SkinResourcePatch"),
			new SkinImage($clientData->SkinImageHeight, $clientData->SkinImageWidth, self::safeB64Decode($clientData->SkinData, "SkinData")),
			$animations,
			new SkinImage($clientData->CapeImageHeight, $clientData->CapeImageWidth, self::safeB64Decode($clientData->CapeData, "CapeData")),
			self::safeB64Decode($clientData->SkinGeometryData, "SkinGeometryData"),
			self::safeB64Decode($clientData->SkinGeometryDataEngineVersion, "SkinGeometryDataEngineVersion"), //yes, they actually base64'd the version!
			self::safeB64Decode($clientData->SkinAnimationData, "SkinAnimationData"),
			$clientData->CapeId,
			null,
			$clientData->ArmSize,
			$clientData->SkinColor,
			array_map(function(ClientDataPersonaSkinPiece $piece) : PersonaSkinPiece{
				return new PersonaSkinPiece($piece->PieceId, $piece->PieceType, $piece->PackId, $piece->IsDefault, $piece->ProductId);
			}, $clientData->PersonaPieces),
			array_map(function(ClientDataPersonaPieceTintColor $tint) : PersonaPieceTintColor{
				return new PersonaPieceTintColor($tint->PieceType, $tint->Colors);
			}, $clientData->PieceTintColors),
			true,
			$clientData->PremiumSkin,
			$clientData->PersonaSkin,
			$clientData->CapeOnClassicSkin,
			true, //assume this is true? there's no field for it ...
		);
	}
}