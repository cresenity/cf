<?php declare(strict_types = 1);

namespace PHPStan\Analyser;

use PHPStan\Collectors\CollectedData;
use PHPStan\Dependency\RootExportedNode;

class FileAnalyserResult
{

	/**
	 * @param list<Error> $errors
	 * @param list<CollectedData> $collectedData
	 * @param list<string> $dependencies
	 * @param list<RootExportedNode> $exportedNodes
	 */
	public function __construct(
		private array $errors,
		private array $collectedData,
		private array $dependencies,
		private array $exportedNodes,
	)
	{
	}

	/**
	 * @return list<Error>
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * @return list<CollectedData>
	 */
	public function getCollectedData(): array
	{
		return $this->collectedData;
	}

	/**
	 * @return list<string>
	 */
	public function getDependencies(): array
	{
		return $this->dependencies;
	}

	/**
	 * @return list<RootExportedNode>
	 */
	public function getExportedNodes(): array
	{
		return $this->exportedNodes;
	}

}
