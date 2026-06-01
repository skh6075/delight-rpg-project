<?php

namespace alvin0319\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Expr\YieldFrom;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\TryCatch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function str_contains;

/**
 * @implements Rule<FunctionLike>
 */
class YieldInTryCatchRule implements Rule{
	public function getNodeType() : string{
		return FunctionLike::class;
	}

	public function processNode(Node $node, Scope $scope) : array{
		// 함수 본문이 없거나(인터페이스 등) 추상 메서드인 경우 패스
		$stmts = $node->getStmts();
		if($stmts === null){
			return [];
		}

		// PHPDoc 확인:
		// - @throws: 예외를 전파하는 함수로 간주하여 검사 제외
		// - @safe-generator 또는 @phpstan-safe-generator: 내부에서 모든 예외를 처리하는 안전한 generator로 간주
		$docComment = $node->getDocComment();
		if($docComment !== null){
			$docText = $docComment->getText();
			if(str_contains($docText, '@throws')
				|| str_contains($docText, '@safe-generator')
				|| str_contains($docText, '@phpstan-safe-generator')){
				return [];
			}
		}

		return $this->findUnwrappedYields($stmts, false);
	}

	/**
	 * 노드 트리를 순회하며 try 블록 밖의 yield를 찾습니다.
	 *
	 * @param Node[]|Node $nodes
	 * @param bool        $inTry 현재 try 블록 내부인지 여부
	 *
	 * @return list<IdentifierRuleError> 에러 배열
	 */
	private function findUnwrappedYields($nodes, bool $inTry) : array{
		$errors = [];

		// 단일 노드일 경우 배열로 변환하여 처리
		if(!is_array($nodes)){
			$nodes = [$nodes];
		}

		foreach($nodes as $node){
			if($node === null){ // @phpstan-ignore-line
				continue;
			}
			if($node instanceof FunctionLike || $node instanceof ClassLike){
				continue;
			}

			if($node instanceof Yield_ || $node instanceof YieldFrom){
				if(!$inTry){
					$type = $node instanceof YieldFrom ? 'yield from' : 'yield';
					$errors[] = RuleErrorBuilder::message(sprintf(
						'Generator usage "%s" must be enclosed in a try-catch block to handle potential exceptions.',
						$type
					))
						->identifier("generator.yieldInTryCatch")
						->line($node->getStartLine())->build();
				}
			}

			if($node instanceof TryCatch){
				// Try 블록: inTry = true로 설정하여 내부 탐색
				$errors = array_merge($errors, $this->findUnwrappedYields($node->stmts, true));

//				// Catch 블록: 여기서 yield를 쓴다면 그것 또한 새로운 try로 감싸져야 안전하므로 inTry = false
				// 2026.01.07: catch에서 try-catch 쓰는건 코드 가독성이 안 좋아져서 비활성화.
//				foreach($node->catches as $catch){
//					$errors = array_merge($errors, $this->findUnwrappedYields($catch->stmts, false));
//				}

				// Finally 블록: 여기서 yield를 쓰는 것은 드물지만, 쓴다면 보호받지 못하므로 inTry = false
				if($node->finally){
					$errors = array_merge($errors, $this->findUnwrappedYields($node->finally->stmts, false));
				}

				// TryCatch 노드 자체의 처리는 끝났으므로 continue (중복 탐색 방지)
				continue;
			}

			// 4. 그 외 일반 노드: 자식 노드(서브 노드)들을 재귀적으로 탐색
			// (예: $x = yield $y; 같은 표현식 내부 탐색을 위해)
			foreach($node->getSubNodeNames() as $propName){
				$subNode = $node->$propName;
				if(is_array($subNode) || $subNode instanceof Node){
					$errors = array_merge($errors, $this->findUnwrappedYields($subNode, $inTry));
				}
			}
		}

		return $errors;
	}
}
