<?php

declare(strict_types=1);

namespace Artisense\Support\Formatters;

use Artisense\Contracts\OutputFormatterContract;
use Artisense\Exceptions\OutputFormatterException;

final class GlowOutputFormatter implements OutputFormatterContract
{
    /**
     * @throws OutputFormatterException
     */
    public function format(string $output): string
    {
        // Define how input/output streams will be handled for the child process
        $descriptors = [
            0 => ['pipe', 'r'], // stdin: we will write the markdown content to this
            1 => ['pipe', 'w'], // stdout: we will read the rendered output from this
            2 => ['pipe', 'w'], // stderr: for errors (we won't use the content here)
        ];

        // Start the glow process with given I/O stream definitions
        $process = proc_open('glow --style=dark --width=120', $descriptors, $pipes);

        /** @var resource[] $coercedPipes */
        $coercedPipes = $pipes;

        // If the process failed to start, just return the original unformatted output
        if (! is_resource($process)) {
            return $output;
        }

        // Write the markdown content to glow's stdin
        fwrite($coercedPipes[0], $output);
        fclose($coercedPipes[0]); // Close stdin to signal we're done sending input

        // Read the formatted output from glow's stdout
        $formatted = stream_get_contents($coercedPipes[1]);

        if ($formatted === false) {
            throw new OutputFormatterException('Failed to read formatted output from glow.');
        }

        fclose($coercedPipes[1]);

        // Close stderr even though we're not using it
        fclose($coercedPipes[2]);

        // Close the process and clean up resources
        proc_close($process);

        // Return the formatted markdown rendered by glow
        return $formatted;
    }
}
