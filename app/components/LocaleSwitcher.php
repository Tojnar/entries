<?php

declare(strict_types=1);

namespace App\Components;

use Nette\Application\UI\Control;

/**
 * Control for switching locale of the application.
 */
class LocaleSwitcher extends Control {
	/** @var string[] */
	private $locales;

	public function __construct(array $locales, ?array $allowedLocales) {
		if ($allowedLocales === null) {
			$this->locales = $locales;
		} else {
			$this->locales = array_filter($locales, function(string $code) use ($allowedLocales): bool {
				return \in_array($code, $allowedLocales, true);
			}, \ARRAY_FILTER_USE_KEY);
		}
	}

	public function render(): void {
		/** @var \Nette\Bridges\ApplicationLatte\DefaultTemplate */
		$template = $this->template;

		$template->locales = $this->locales;

		$template->render(__DIR__ . '/LocaleSwitcher.latte');
	}
}
