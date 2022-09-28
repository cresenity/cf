<?php

class CDaemon_Supervisor_QueueCommandString {
    /**
     * Get the additional option string for the worker command.
     *
     * @param \CDaemon_Supervisor_SupervisorOptions $options
     *
     * @return string
     */
    public static function toWorkerOptionsString(CDaemon_Supervisor_SupervisorOptions $options) {
        return sprintf(
            '--name=%s --supervisor=%s %s',
            $options->workersName,
            $options->name,
            static::toOptionsString($options)
        );
    }

    /**
     * Get the additional option string for the supervisor command.
     *
     * @param \CDaemon_Supervisor_SupervisorOptions $options
     *
     * @return string
     */
    public static function toSupervisorOptionsString(CDaemon_Supervisor_SupervisorOptions $options) {
        return sprintf(
            '--workers-name=%s --balance=%s --max-processes=%s --min-processes=%s --nice=%s --balance-cooldown=%s --balance-max-shift=%s --parent-id=%s %s',
            $options->workersName,
            $options->balance,
            $options->maxProcesses,
            $options->minProcesses,
            $options->nice,
            $options->balanceCooldown,
            $options->balanceMaxShift,
            $options->parentId,
            static::toOptionsString($options)
        );
    }

    /**
     * Get the additional option string for the command.
     *
     * @param \CDaemon_Supervisor_SupervisorOptions $options
     * @param bool                                  $paused
     *
     * @return string
     */
    public static function toOptionsString(CDaemon_Supervisor_SupervisorOptions $options, $paused = false) {
        $string = sprintf(
            '--backoff=%s --max-time=%s --max-jobs=%s --memory=%s --queue="%s" --sleep=%s --timeout=%s --tries=%s --rest=%s',
            $options->backoff,
            $options->maxTime,
            $options->maxJobs,
            $options->memory,
            $options->queue,
            $options->sleep,
            $options->timeout,
            $options->maxTries,
            $options->rest
        );

        if ($options->force) {
            $string .= ' --force';
        }

        if ($paused) {
            $string .= ' --paused';
        }

        return $string;
    }
}
