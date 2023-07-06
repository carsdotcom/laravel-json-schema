<?php

namespace Carsdotcom\JsonSchemaValidation\Traits;

trait VerboseLineTrait
{
    /*
     * This trait works with Commands to provide a one-stop
     * "if verbose, then output this"
     */

    public function verbose($output)
    {
        if ($this->option('verbose')) {
            $this->line($output);
        }
    }

    public function debug($output)
    {
        if ($this->output->isDebug()) {
            $this->line($output);
        }
    }
}