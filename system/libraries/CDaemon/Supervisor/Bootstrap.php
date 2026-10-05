<?php

class CDaemon_Supervisor_Bootstrap {
    protected static $booted = false;

    public static function boot() {
        if (!static::$booted) {
            //boot logic
            if (!CF::config('daemon.supervisor.enabled')) {
                return;
            }
            $connection = CF::config('daemon.supervisor.use', 'default');

            if (!is_null($config = CDatabase::manager()->getConfig("redis.clusters.{$connection}.0"))) {
                CDatabase::manager()->getConfig(["database.redis.{$connection}" => $config]);
            } elseif (is_null($config) && is_null($config = CDatabase::manager()->getConfig("redis.{$connection}"))) {
                throw new Exception("Redis connection [{$connection}] has not been configured.");
            }

            $config['options']['prefix'] = CF::config('daemon.supervisor.prefix') ?: 'supervisor:';

            CDatabase::manager()->addRedisConnection($config, 'supervisor');

            $queuer = CQueue::queuer();
            $queuer->addConnector('redis', function () {
                return new CDaemon_Supervisor_Queue_RedisConnector(CRedis::instance());
            });
            $queuer->addConnector('database', function () {
                return new CDaemon_Supervisor_Queue_DatabaseConnector();
            });
            static::listenForEvents();

            if (CF::config('daemon.supervisor.metrics.cron.enabled', false)) {
                $cronExpression = CF::config('daemon.supervisor.metrics.cron.expression', '*/5 * * * *');
                c::cron()->job(new CDaemon_Supervisor_TaskQueue_SupervisorSnapshot())->cron($cronExpression);
            }
            static::$booted = true;
        }
    }

    private static function listenForEvents() {
        $events = [
            CDaemon_Supervisor_Event_RedisEvent_JobPushed::class => [
                CDaemon_Supervisor_Listener_StoreJob::class,
                CDaemon_Supervisor_Listener_StoreMonitoredTags::class,
            ],

            CDaemon_Supervisor_Event_RedisEvent_JobReserved::class => [
                CDaemon_Supervisor_Listener_MarkJobAsReserved::class,
                CDaemon_Supervisor_Listener_StartTimingJob::class,
            ],

            CDaemon_Supervisor_Event_RedisEvent_JobReleased::class => [
                CDaemon_Supervisor_Listener_MarkJobAsReleased::class,
            ],

            CDaemon_Supervisor_Event_RedisEvent_JobDeleted::class => [
                CDaemon_Supervisor_Listener_MarkJobAsComplete::class,
                CDaemon_Supervisor_Listener_UpdateJobMetrics::class,
            ],

            CDaemon_Supervisor_Event_JobsMigrated::class => [
                CDaemon_Supervisor_Listener_MarkJobsAsMigrated::class,
            ],

            CQueue_Event_JobExceptionOccurred::class => [
                CDaemon_Supervisor_Listener_ForgetJobTimer::class,
            ],
            CQueue_Event_JobFailed::class => [
                CDaemon_Supervisor_Listener_ForgetJobTimer::class,
                CDaemon_Supervisor_Listener_MarshalFailedEvent::class,
            ],

            CDaemon_Supervisor_Event_RedisEvent_JobFailed::class => [
                CDaemon_Supervisor_Listener_MarkJobAsFailed::class,
                CDaemon_Supervisor_Listener_StoreTagsForFailedJob::class,
            ],

            CDaemon_Supervisor_Event_MasterSupervisorLooped::class => [
                CDaemon_Supervisor_Listener_TrimRecentJobs::class,
                CDaemon_Supervisor_Listener_TrimFailedJobs::class,
                CDaemon_Supervisor_Listener_TrimMonitoredJobs::class,
                CDaemon_Supervisor_Listener_ExpireSupervisors::class,
                CDaemon_Supervisor_Listener_MonitorMasterSupervisorMemory::class,
            ],

            CDaemon_Supervisor_Event_SupervisorLooped::class => [
                CDaemon_Supervisor_Listener_PruneTerminatingProcesses::class,
                CDaemon_Supervisor_Listener_MonitorSupervisorMemory::class,
                CDaemon_Supervisor_Listener_MonitorWaitTimes::class,
            ],

            CDaemon_Event_WorkerProcessRestarting::class => [

            ],

            CDaemon_Supervisor_Event_SupervisorProcessRestarting::class => [

            ],

            CDaemon_Supervisor_Event_LongWaitDetected::class => [
                //CDaemon_Supervisor_Listener_SendNotification::class,
            ],
        ];

        foreach ($events as $event => $listeners) {
            foreach ($listeners as $listener) {
                CEvent::dispatcher()->listen($event, $listener);
            }
        }
    }
}
