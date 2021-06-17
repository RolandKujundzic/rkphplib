<?php

namespace rkphplib;

require_once __DIR__.'/File.php';



/**
 * Wrapper to pipe process execution (proc_[open|close|get_status]).
 * Always call close() to retrieve [ retval, error, output ].
 * If retval == 0 execution failed.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class PipeExecute {

// @var vector<vector> $descriptor [ input, output, error ]
protected $descriptor = [];

// @var vectory<resource> $pipe
protected $pipe = [];

// @var resource $process
protected $process = null;

// @var string $command
protected $command = null;


/**
 * Execute command via pipe. Write to pipe[0] read from pipe[1] (output) and
 * pipe[2] (errors). Process will read from 0 and write to 1 and 2.
 */
public function __construct(string $command, array $parameter = []) {
	$this->descriptor = [
		0 => [ 'pipe', 'r' ],
		1 => [ 'pipe', 'w' ],
		2 => [ 'pipe', 'w' ]
	];

  if (empty($command) || !is_string($command)) {
    throw new Exception('invalid command', print_r($command, true));
  }

  if (is_array($parameter)) {
		foreach ($parameter as $key => $value) {
			$command = str_replace(TAG_PREFIX.$key.TAG_SUFFIX, escapeshellarg($value), $command);
		}
	}

	$this->process = proc_open($command, $this->descriptor, $this->pipe); 
	if (!is_resource($this->process)) {
		throw new Exception('pipe to command failed', $command);
	}

	for ($i = 0; $i < 3; $i++) {
		if (!is_resource($this->pipe[$i])) {
			throw new Exception("command pipe[$i] is invalid", $command);
		}

		// wait max. 2 sec for stream + set streams to non-blocking
		stream_set_timeout($this->pipe[$i], 2);
		stream_set_blocking($this->pipe[$i], false);
	}

	// \rkphplib\lib\log_debug("PipeExecute.__construct:67> $command");
	$this->command = $command;
}


/**
 * Return stream contents of pipe[num] (default is pipe[1] = output).
 */
private function readStream(int $num = 1) : ?string {
	$res = stream_get_contents($this->pipe[$num]);
	// \rkphplib\lib\log_debug("PipeExecute.readStream:77> num=$num res=[$res]");
	return $res;
}


/**
 * Write to pipe. Close is necessary.
 */
public function write(string $txt) : void {
	// \rkphplib\lib\log_debug("PipeExecute.write:86> $txt");
	if (fwrite($this->pipe[0], $txt) === false) {
		$log_txt = (strlen($txt) > 180) ? substr($txt, 0, 20).' ... '.substr($txt, -20) : $txt;
		throw new Exception('write failed', "cmd=[".$this->command."] txt=[$log_txt]");
	}
}


/**
 * Pipe in $file. Close is necessary.
 */
public function load(string $file) : void {
	$fh = File::open($file, 'rb');
	// \rkphplib\lib\log_debug("PipeExecute.load:99> open file=[$file] fh=[$fh]\n");
	while (!feof($fh)) { 
		if (($buffer = fread($fh, 4096)) === false) {
			throw new Exception('fread file failed', "file=$file buffer=$buffer");
		}
		else {
			File::write($this->pipe[0], $buffer); 
		}
	}
}


/**
 * When finished write/load you must call close() to retrieve
 * [ retval, error, output ].
 */
public function close(bool $abort = false) : array {

	if (!is_resource($this->process)) {
		throw new Exception('invalid process - close() already called?');
	}

	if (fclose($this->pipe[0]) === false) {
		throw new Exception('close pipe[0] failed', $this->command);
	}

	$output = $this->readStream(1);
	$error = $this->readStream(2);

	for ($i = 1; $i < 3; $i++) {
		if (fclose($this->pipe[$i]) === false) {
			throw new Exception("close pipe[$i] failed", $this->command);
		}
	}

	$retval = proc_close($this->process);
	// \rkphplib\lib\log_debug("PipeExecute.close:135> retval=[$retval] output=[$output] error=[$error]");

	if ($retval === -1) {
		if ($abort) {
			throw new Exception('pipe command failed', $this->command);
		}
		else {
			$retval = 0;
		}
	}

	return [ $retval, $error, $output ];
}


}

