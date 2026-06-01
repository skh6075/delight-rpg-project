<?php

declare(strict_types=1);

namespace alvin0319\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * @implements Rule<Expression>
 */
class UnusedGeneratorReturnValueRule implements Rule{
	public function getNodeType() : string{
		// 단독으로 사용된 표현식(문장)만 검사합니다.
		// 예: $this->myGenerator(); (O)
		// 예: $a = $this->myGenerator(); (X - 대입문이므로 Expression 노드가 아님)
		return Expression::class;
	}

	public function processNode(Node $node, Scope $scope) : array{
		// 표현식 내부가 함수, 메서드, 정적 메서드 호출인지 확인합니다.
		if(
			!$node->expr instanceof FuncCall &&
			!$node->expr instanceof MethodCall &&
			!$node->expr instanceof StaticCall
		){
			return [];
		}

		// 호출된 함수의 반환 타입을 추론합니다.
		$returnType = $scope->getType($node->expr);

		// 반환 타입이 \Generator 클래스(혹은 하위 클래스)인지 확인합니다.
		$generatorType = new ObjectType(\Generator::class);

		// Generator 타입이 반환 타입의 상위 타입(super type)인지 확인합니다.
		if($generatorType->isSuperTypeOf($returnType)->yes()){
			return [
				RuleErrorBuilder::message('Generator function called without using its return value. The generator code will not execute.')
					->identifier("generator.unusedReturnType")
					->line($node->getLine())
					->build()
			];
		}

		return [];
	}
}
