<?php

namespace Ssh;

use RuntimeException;

/**
 * Wrapper for ssh2_shell
 *
 * @author Dimitri Gilbert <dimitri.gilbert@gmail.com>
 */
class Shell extends Subsystem
{
    protected $shell = null;
    protected $sudo = null;

    protected function createResource()
    {
        $this->resource = $this->getSessionResource();
    }

    /**
     * Open a new shell.
     *
     * @param string $termType The type of shell emulator
     * @param array $env Environment variable as key value pair
     * @param int $width
     * @param int $height
     * @param int $width_height_type
     * @return void
     */
    public function open(
        string $termType = 'vanilla',
        array $env = array(),
        int $width = 80,
        int $height = 25,
        int $width_height_type = SSH2_TERM_UNIT_CHARS
    )
    {
        $this->shell = ssh2_shell(
            $this->getResource(),
            $termType,
            $env,
            $width,
            $height,
            $width_height_type
        );
        stream_set_blocking($this->shell, false);
    }

    /**
     * go to end of stream
     *
     * @param int $sleep time to sleep before going to the end of stream
     * @return void
     */
    public function eos(int $sleep = 1)
    {
        $this->read(0, $sleep);
    }

    /**
     * send an input to the shell
     *
     * @param string $input
     * @param int $sleep
     * @return boolean
     */
    public function send(string $input, int $sleep = 1)
    {
        $this->eos($sleep);
        $input .= PHP_EOL;
        for ($written = 0; $written < strlen($input); $written += $fwrite) {
            $fwrite = fwrite($this->shell, substr($input, $written));
            if ($fwrite === false) {
                throw new \Exception("Error sending input", 1);
            }
        }
        return true;
    }

    /**
     * Read the stream
     *
     * @param int $iteration Try to read this many times.
     * @param int $sleep Time to sleep before trying to read.
     * 
     * @return string
     */
    public function read(int $iteration = 0, int $sleep = 1)
    {
        sleep($sleep);
        $data = stream_get_contents($this->shell);
        if ($iteration !== 0 and !$this->isCommandDone($data)) {
            $iteration--;
            $data .= $this->read($iteration, $sleep);
        }
        return $data;
    }

    /**
     * execute a command with sudo
     *
     * @param string $cmd The command to execute.
     * @param string $password The user password.
     * @param array $options
     * 
     * @return bool
     */
    public function sudo(
        string $cmd,
        string $password,
        array $options = array()
    )
    {
        if (is_null($this->sudo)) {
            $this->sudo = time();
        }
        $ret = $this->send('sudo '.$cmd, 0);
        if (($this->sudo + 600) > time()) {
            $ret = $this->send($password);
        }
        $this->sudo = time();
        return $ret;
    }

    /**
     * Check if command has finished
     *
     * @param string $output Output from a read call.
     * 
     * @return boolean
     */
    public function isCommandDone(string $output)
    {
        $lines = explode("\n", $output);
        $lastLine = $lines[(count($lines)-1)];
        return (preg_match(
            '#^[a-zA-Z]+\@[a-zA-Z]+\:\W+|\w+\$$#',
            $lastLine
        ) === 1);
    }
}
