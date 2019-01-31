<?php

namespace CRUDManager;

/**
 * Trait helper for extracting currently using class name with optional regex pattern.
 * @package CRUDManager
 * @author  Jindřich Máca
 */
trait ClassName
{
	/**
	 * Get currently using class name with optional regex pattern.
	 * @param string|null $pattern optional regex pattern
	 * @return string class name
	 * @throws \UnexpectedValueException If class name does not match the pattern for its extraction.
	 */
	public static final function getClassName(?string $pattern = null): string
	{
		$className = static::class;
		if (is_null($pattern)) return $className;
		if (preg_match($pattern, $className, $matches) === 1) return $matches[1];
		throw new \UnexpectedValueException("Class name '$className' does not match the pattern '$pattern' for its name extraction!");
	}
}
