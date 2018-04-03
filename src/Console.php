<?php
/**
 * It's free open-source software released under the MIT License.
 *
 * @author Anatoly Fenric <anatoly.fenric@gmail.com>
 * @copyright Copyright (c) 2013-2018 by Fenric Laboratory
 * @license https://github.com/fenric/framework/blob/master/LICENSE.md
 * @link https://github.com/fenric/framework
 */

namespace Fenric\Console;

/**
 * Import classes
 */
use Fenric\Collection\Collection;

/**
 * Console
 */
class Console
{

	/**
	 * String styles
	 */
	public const STRING_BOLD        = ['1', '22'];
	public const STRING_FAINT       = ['2', '22'];
	public const STRING_UNDERLINE   = ['4', '24'];
	public const STRING_BLINK       = ['5', '25'];
	public const STRING_REVERSE     = ['7', '27'];
	public const STRING_HIDDEN      = ['8', '28'];

	/**
	 * Foreground colors
	 */
	public const FOREGROUND_BLACK   = ['30', '39'];
	public const FOREGROUND_RED     = ['31', '39'];
	public const FOREGROUND_GREEN   = ['32', '39'];
	public const FOREGROUND_YELLOW  = ['33', '39'];
	public const FOREGROUND_BLUE    = ['34', '39'];
	public const FOREGROUND_PURPLE  = ['35', '39'];
	public const FOREGROUND_CYAN    = ['36', '39'];
	public const FOREGROUND_WHITE   = ['37', '39'];
	public const FOREGROUND_DEFAULT = ['39', '39'];
	public const FOREGROUND_RESET   = ['39', '39'];

	/**
	 * Background colors
	 */
	public const BACKGROUND_BLACK   = ['40', '49'];
	public const BACKGROUND_RED     = ['41', '49'];
	public const BACKGROUND_GREEN   = ['42', '49'];
	public const BACKGROUND_YELLOW  = ['43', '49'];
	public const BACKGROUND_BLUE    = ['44', '49'];
	public const BACKGROUND_PURPLE  = ['45', '49'];
	public const BACKGROUND_CYAN    = ['46', '49'];
	public const BACKGROUND_WHITE   = ['47', '49'];
	public const BACKGROUND_DEFAULT = ['49', '49'];
	public const BACKGROUND_RESET   = ['49', '49'];

	/**
	 * Screen size
	 */
	public $width;
	public $height;

	/**
	 * Command options
	 */
	public $options;

	/**
	 * Command history
	 */
	public $history = [];

	/**
	 * Constructor of the class
	 */
	public function __construct(array $tokens)
	{
		// Contains the file name
		unset($tokens[0]);

		$this->width = exec('tput cols');
		$this->height = exec('tput lines');

		$this->options = new Collection(
			$this->parse($tokens)
		);
	}

	/**
	 * Outputs a string as info line
	 */
	public function info(string $string)
	{
		return $this->line($string, [
			self::FOREGROUND_GREEN,
		]);
	}

	/**
	 * Outputs a string as comment line
	 */
	public function comment(string $string)
	{
		return $this->line($string, [
			self::FOREGROUND_YELLOW,
		]);
	}

	/**
	 * Outputs a string as success block
	 */
	public function success(string $string)
	{
		return $this->block($string, [
			self::FOREGROUND_BLACK,
			self::BACKGROUND_GREEN,
		]);
	}

	/**
	 * Outputs a string as warning block
	 */
	public function warning(string $string)
	{
		return $this->block($string, [
			self::FOREGROUND_BLACK,
			self::BACKGROUND_YELLOW,
		]);
	}

	/**
	 * Outputs a string as error block
	 */
	public function error(string $string)
	{
		return $this->block($string, [
			self::FOREGROUND_WHITE,
			self::BACKGROUND_RED,
		]);
	}

	/**
	 * Outputs a question
	 */
	public function ask(string $question, bool $required = true)
	{
		while (true)
		{
			$this->info($question);
			$this->stdout('> ');

			$input = trim($this->stdin());

			if ($required && strlen($input) === 0)
			{
				continue;
			}

			return $input;
		}
	}

	/**
	 * Outputs a confirmation
	 */
	public function confirm(string $question, bool $default = false)
	{
		while (true)
		{
			$this->info($question);
			$this->stdout('[yes/no] > ');

			$input = trim($this->stdin());

			if (0 === strlen($input))
			{
				return $default;
			}
			if (0 === strncasecmp($input, 'y', 1))
			{
				return true;
			}
			if (0 === strncasecmp($input, 'n', 1))
			{
				return false;
			}
		}
	}

	/**
	 * Outputs a progress
	 */
	public function progress(string $format, float $size)
	{
		return function(float $step = 0) use($size, $format) : void
		{
			static $length = 0;

			$progress = $step / $size;

			$percentage = round($progress * 100);

			$output = strtr($format, [
				'{percentage}' => $percentage,
			]);

			$this->line(! $length ? $output : sprintf(
				"\033[1A\033[%1\$dD%2\$s", $length, $output
			));

			$length = $this->length($output);
		};
	}

	/**
	 * Outputs a block
	 */
	public function block(string $string, array $styles = [])
	{
		$width = 100;
		$padding = 1;

		if ($width > $this->width) {
			$width = $this->width;
		}

		$lines = explode(PHP_EOL, PHP_EOL . wordwrap(trim($string), $width - ($padding * 2), PHP_EOL, true) . PHP_EOL);

		foreach ($lines as & $line)
		{
			$line = $this->style(str_repeat(' ', $padding) . trim($line) . str_repeat(' ', $width - $padding - $this->length(trim($line))), $styles);
		}

		$lines = PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL . PHP_EOL;

		if (count($this->history) > 0)
		{
			$lines = str_repeat(PHP_EOL, 2 - substr_count(substr(end($this->history), -2), PHP_EOL)) . ltrim($lines);
		}

		return $this->stdout($lines);
	}

	/**
	 * Outputs a line
	 */
	public function line(string $string, array $styles = [])
	{
		$string = $this->style($string, $styles);

		return $this->stdout($string . PHP_EOL);
	}

	/**
	 * Outputs end of line character
	 */
	public function eol(int $count)
	{
		return $this->stdout(
			str_repeat(PHP_EOL, $count)
		);
	}

	/**
	 * Reads the stdin stream
	 */
	public function stdin()
	{
		return fgets(STDIN);
	}

	/**
	 * Writes to the stdout stream
	 */
	public function stdout(string $string)
	{
		return $this->write(STDOUT, $string);
	}

	/**
	 * Writes to the stderr stream
	 */
	public function stderr(string $string)
	{
		return $this->write(STDERR, $string);
	}

	/**
	 * Writes to stream output
	 */
	public function write($stream, string $string)
	{
		$string = $this->format($string);

		$this->history[] = $string;

		return fwrite($stream, $string);
	}

	/**
	 * Stylizes a string
	 */
	public function style(string $string, array $styles) : string
	{
		$open = $close = [];

		foreach ($styles as $style)
		{
			list($open[], $close[]) = $style;
		}

		$format = "\033\133%sm%s\033\133%sm";

		return sprintf($format, join(';', $open), $string, join(';', $close));
	}

	/**
	 * Unstylizes a string
	 */
	public function unstyle(string $string) : string
	{
		$regexp = "/\033\133[\d;]*\w/";

		return preg_replace($regexp, '', $string);
	}

	/**
	 * Length of a string
	 */
	public function length(string $string) : int
	{
		return mb_strlen(
			$this->unstyle($string)
		);
	}

	/**
	 * Formats a string
	 */
	public function format(string $string) : string
	{
		return $string;
	}

	/**
	 * Parses a tokens
	 */
	public function parse(array $tokens) : array
	{
		$result = [];
		$lastOption = '';

		foreach ($tokens as $token)
		{
			// Short option
			if (strlen($token) > 1)
			{
				if (strcmp($token[0], '-') === 0)
				{
					if (strcmp($token[1], '-') !== 0)
					{
						$options = substr($token, 1);
						$lastOption = substr($token, -1);
						$equalpos = strpos($token, '=');
						$value = true;

						if ($equalpos !== false)
						{
							$options = substr($token, 1, $equalpos - 1);
							$lastOption = '';
							$value = substr($token, $equalpos + 1);
						}

						$length = strlen($options);

						for ($i = 0; $i < $length; $i++)
						{
							$result[$options[$i]] = $value;
						}

						continue;
					}
				}
			}

			// Long option
			if (strlen($token) > 2)
			{
				if (strcmp($token[0], '-') === 0)
				{
					if (strcmp($token[1], '-') === 0)
					{
						$option = substr($token, 2);
						$lastOption = substr($token, 2);
						$equalpos = strpos($token, '=');
						$value = true;

						if ($equalpos !== false)
						{
							$option = substr($token, 2, $equalpos - 2);
							$lastOption = '';
							$value = substr($token, $equalpos + 1);
						}

						$result[$option] = $value;

						continue;
					}
				}
			}

			// Last option value
			if (strlen($lastOption) > 0)
			{
				$result[$lastOption] = $token;
				$lastOption = '';

				continue;
			}

			// Other arguments
			$result[] = $token;
		}

		return $result;
	}

	/**
	 * Handles the exception
	 */
	public function throws(\Throwable $e) : void
	{
		$output[] = sprintf('Caught exception [%s]', get_class($e));
		$output[] = '';
		$output[] = $e->getMessage();
		$output[] = '';
		$output[] = sprintf('%s on line %d', $e->getFile(), $e->getLine());
		$output[] = '';
		$output[] = $e->getTraceAsString();

		$this->block(implode(PHP_EOL, $output), [
			self::FOREGROUND_WHITE,
			self::BACKGROUND_RED,
		]);
	}
}
