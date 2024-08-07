<?php

namespace GameNation\Lib\Facebook;

use GameNation\Lib\Facebook\Models\EventContext;
use Lib\Db;

class EventQueue
{
    private const QueueLimit = 10;
    private array $valuesSet = [];
    private $valueSetCount = 0;

    public function __construct(private ?Db $db = null)
    {
        if($this->db === null) {
            $this->db = DB::Get();
        }
    }

    public function QueuePush(EventContext $context): self
    {
        $serializeData = serialize($context->contents);
        $jsonAdHocData = json_encode($context->adHoc);
        $this->valuesSet = array_merge($this->valuesSet, [$context->eventName, $serializeData, $context->fbp, $context->fbc, $context->userAgent, $context->clientIP, $context->actionSource, $context->value, $context->currency, $context->locationURL, $context->eventTime, $jsonAdHocData]);
        $this->valueSetCount++;
        if ($this->valueSetCount >= self::QueueLimit) {
            $this->DumpQueue();
        }
        return $this;
    }

    public function DumpQueue()
    {
        $qs = 'INSERT INTO FBEvents(`EventName`, `Data`, `FBP`, `FBC`, `UserAgent`, `ClientIP`, `ActionSource`, `Value`, `Currency`, `LocationURL`, `EventTime`, `AdHoc`) 
                VALUES '. trim(str_repeat('(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?),', $this->valueSetCount), ', ');
        $result = $this->db->PrepareAndExecute($qs, $this->valuesSet);
        if($result > 0) {
            $this->valuesSet = [];
            $this->valueSetCount = 0;
            return true;
        } else {
            return false;
        }
    }

    public static function Push(EventContext $context)
    {
        $serializeData = serialize($context->contents);
        $jsonAdHocData = json_encode($context->adHoc);
        $Sql = 'INSERT INTO FBEvents(`EventName`, `Data`, `FBP`, `FBC`, `UserAgent`, `ClientIP`, `ActionSource`, `Value`, `Currency`, `LocationURL`, `EventTime`, `AdHoc`) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        return \Lib\Db::Get()->PrepareAndExecute($Sql, [$context->eventName, $serializeData, $context->fbp, $context->fbc, $context->userAgent, $context->clientIP, $context->actionSource, $context->value, $context->currency, $context->locationURL, $context->eventTime, $jsonAdHocData]);
    }

    public static function GetEventByStatusAndMaxId(array $status, int $limit, int $maxId)
    {
        $Sql = 'SELECT * FROM FBEvents WHERE `Status` IN (' . implode(',', array_fill(0, count($status), '?')) . ')';
        $Sql .= ' AND Id <= ' . intval($maxId);
        $Sql .= ' LIMIT ' . intval($limit);
        $response = \Lib\Db::Get()->PrepareAndExecute($Sql, $status);
        if (is_array($response) && count($response) > 0) {
            return $response;
        } else {
            return [];
        }
    }

    public static function GetCurrentMaxId(array $status)
    {
        $Sql = 'SELECT MAX(Id) as MaxId FROM FBEvents WHERE `Status` IN (' . implode(',', array_fill(0, count($status), '?')) . ')';
        $response = \Lib\Db::Get()->PrepareAndExecute($Sql, $status);
        if (is_array($response) && count($response) > 0) {
            return $response;
        } else {
            return [];
        }
    }

    public static function UpdateEventStatus(array $Id, int $status)
    {
        $Sql = 'UPDATE FBEvents SET `Status` = ? WHERE Id IN (' . implode(',', array_fill(0, count($Id), '?')) . ')';
        $result = \Lib\Db::Get()->PrepareAndExecute($Sql, array_merge([$status], $Id));
        if ($result > 0) {
            return $result;
        } else {
            return null;
        }
    }
}
