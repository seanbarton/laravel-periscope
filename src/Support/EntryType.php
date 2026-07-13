<?php

namespace TortoiseIT\LaravelPeriscope\Support;

enum EntryType: string
{
    case Batch = 'batch';
    case Cache = 'cache';
    case ClientRequest = 'client_request';
    case Command = 'command';
    case Debugbar = 'debugbar';
    case Dump = 'dump';
    case Event = 'event';
    case Exception = 'exception';
    case Gate = 'gate';
    case Job = 'job';
    case Log = 'log';
    case Mail = 'mail';
    case Model = 'model';
    case Notification = 'notification';
    case Query = 'query';
    case Redis = 'redis';
    case Request = 'request';
    case Schedule = 'schedule';
    case View = 'view';

    public static function labelFor(string $type): string
    {
        return self::tryFrom($type)?->label() ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function iconFor(string $type): string
    {
        return self::tryFrom($type)?->icon() ?? 'circle';
    }

    public function label(): string
    {
        return match ($this) {
            self::ClientRequest => 'HTTP Client',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Batch => 'layers',
            self::Cache => 'rocket',
            self::ClientRequest => 'globe',
            self::Command => 'terminal',
            self::Debugbar => 'gauge',
            self::Dump => 'braces',
            self::Event => 'zap',
            self::Exception => 'alert',
            self::Gate => 'lock',
            self::Job => 'briefcase',
            self::Log => 'file-text',
            self::Mail => 'mail',
            self::Model => 'database',
            self::Notification => 'bell',
            self::Query => 'table',
            self::Redis => 'server',
            self::Request => 'route',
            self::Schedule => 'clock',
            self::View => 'layout',
        };
    }
}
