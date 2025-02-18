<?php

declare(strict_types=1);

namespace App\Exporters;

use App;
use App\Helpers\CsvWriter;
use App\Model\Team;
use App\Templates\Filters\CategoryFormatFilter;
use Nextras\Orm\Collection\ICollection;
use function nspl\a\map;
use function nspl\a\reduce;

/**
 * Legacy CSV Exporter.
 *
 * The output is generic CSV file – values separated by commas. The first line
 * contains heading (comma separated list of column names).
 *
 * Each following row represents one team. First four columns are id, team name,
 * date the team was registered and category. Those are followed by arbitrary
 * number of columns as configured by user. The event sets the maximum number
 * of team members. For each of these possible team member there are at least
 * four columns: last name, first name, gender and birth date. Between the birth
 * date and gender, there can be also arbitrary amount of custom columns
 * (as configured by user). The last column contains the status of the team
 * (i.e. either registered, or paid).
 */
class CsvExporter implements IExporter {
	/** @var Team[]|ICollection */
	private $teams;

	/** @var App\Model\CountryRepository */
	private $countries;

	/** @var array */
	private $teamFields;

	/** @var array */
	private $personFields;

	/** @var CategoryFormatFilter */
	private $categoryFormatter;

	/**
	 * @param Team[]|ICollection $teams
	 */
	public function __construct(ICollection $teams, App\Model\CountryRepository $countries, array $teamFields, array $personFields, CategoryFormatFilter $categoryFormatter) {
		$this->teams = $teams;
		$this->countries = $countries;
		$this->teamFields = $teamFields;
		$this->personFields = $personFields;
		$this->categoryFormatter = $categoryFormatter;
	}

	public function getMimeType(): string {
		return 'text/csv';
	}

	public function output(): void {
		$fp = fopen('php://output', 'a');
		if ($fp === false) {
			throw new \PHPStan\ShouldNotHappenException();
		}
		$writer = new CsvWriter($fp);
		$writer->addColumns(['#', 'name', 'registered', 'category', 'message']);
		$maxMembers = reduce(function($maximum, $personsCount) {
			return max($maximum, $personsCount);
		}, map(function($team) {
			return $team->persons->count();
		}, $this->teams));

		foreach ($this->teamFields as $name => $field) {
			if ($field['type'] === 'checkboxlist') {
				$writer->addColumns(array_map(function($itemKey) use ($name) {
					return $name . '-' . $itemKey;
				}, array_keys($field['items'])));
			} else {
				$writer->addColumns($name);
			}
		}

		for ($i = 1; $i <= $maxMembers; ++$i) {
			$writer->addColumns([
				'm' . $i . 'lastname',
				'm' . $i . 'firstname',
				'm' . $i . 'email',
				'm' . $i . 'gender',
			]);
			foreach ($this->personFields as $name => $field) {
				if ($field['type'] === 'checkboxlist') {
					$writer->addColumns(array_map(function($itemKey) use ($i, $name) {
						return 'm' . $i . $name . '-' . $itemKey;
					}, array_keys($field['items'])));
				} else {
					$writer->addColumns('m' . $i . $name);
				}
			}
			$writer->addColumns('m' . $i . 'birth');
		}

		$writer->addColumns('status');
		$writer->writeHeaders();

		foreach ($this->teams as $team) {
			$row = [
				'#' => $team->id,
				'name' => $team->name,
				'registered' => $team->timestamp,
				'category' => $this->categoryFormatter->__invoke($team),
				'message' => $team->message,
			];
			$row += $this->addCustomFields($this->teamFields, $team);
			$i = 0;
			foreach ($team->persons as $person) {
				++$i;
				$row['m' . $i . 'lastname'] = $person->lastname;
				$row['m' . $i . 'firstname'] = $person->firstname;
				$row['m' . $i . 'email'] = $person->email;
				$row['m' . $i . 'gender'] = $person->gender;
				$row += $this->addCustomFields($this->personFields, $person, 'm' . $i);
				$row['m' . $i . 'birth'] = $person->birth;
			}
			$row['status'] = $team->status;
			$writer->write($row);
		}
		fclose($fp);
		exit;
	}

	/**
	 * @param App\Model\Team|App\Model\Person $container
	 */
	private function addCustomFields(array $fields, $container, string $prefix = ''): array {
		$row = [];
		foreach ($fields as $name => $field) {
			$f = isset($container->getJsonData()->$name) ? $container->getJsonData()->$name : null;
			if ($f) {
				if ($field['type'] === 'country') {
					$country = $this->countries->getByIdChecked($f);
					$row[$prefix . $name] = $country->name;
				} elseif ($field['type'] === 'checkboxlist') {
					foreach ($field['items'] as $itemKey => $_) {
						$row[$prefix . $name . '-' . $itemKey] = \in_array($itemKey, $f, true);
					}
				} elseif ($field['type'] === 'sportident') {
					$row[$prefix . $name] = $f->cardId ?? 'rent';
				} else {
					$row[$prefix . $name] = $f;
				}
			}
		}

		return $row;
	}
}
