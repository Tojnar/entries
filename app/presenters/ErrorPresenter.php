<?php

namespace App\Presenters;

use App\LimitedAccessException;
use Exception;
use Nette;
use Tracy\Debugger;

class ErrorPresenter extends BasePresenter {
	/**
	 * @return void
	 */
	public function renderDefault(Exception $exception) {
		if ($exception instanceof LimitedAccessException) {
			$code = $exception->getCode();
			$errorType = $code === LimitedAccessException::LATE ? 'late' : 'early';

			$this->setView('access');

			$fmt = $this->translator->translate('messages.date');
			$this->template->openingDate = $this->context->parameters['entries']['opening']->format($fmt);

			$this->template->errorType = $errorType;
		} elseif ($exception instanceof Nette\Application\BadRequestException) {
			$code = $exception->getCode();
			// load template 403.latte or 404.latte or ... 4xx.latte
			$this->setView(in_array($code, [403, 404, 405, 410, 500], true) ? $code : '4xx');
			// log to access.log
			Debugger::log("HTTP code $code: {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}", 'access');
		} else {
			$this->setView('500'); // load template 500.latte
			Debugger::log($exception, Debugger::EXCEPTION); // and log exception
		}

		if ($this->isAjax()) { // AJAX request? Note this error in payload.
			$this->payload->error = true;
			$this->terminate();
		}
	}
}
