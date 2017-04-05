<?php
namespace ESN\CalDAV;

use \Sabre\DAV\Server;
use \Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\VObject\Document;
use Sabre\Uri;
use Sabre\Event\EventEmitter;

class NewRealTime extends ServerPlugin {

    protected $server;
    protected $message;
    protected $body;
    protected $eventEmitter;

    private $CALENDAR_TOPICS = [
        'CALENDAR_CREATED' => 'calendar:calendar:created',
        'CALENDAR_UPDATED' => 'calendar:calendar:updated',
        'CALENDAR_DELETED' => 'calendar:calendar:deleted',
    ];

    function __construct($client, $eventEmitter) {
        $this->client = $client;
        $this->eventEmitter = $eventEmitter;
    }

    function initialize(Server $server) {
        $this->server = $server;
        $this->messages = array();
        $this->body = array();

        $this->eventEmitter->on('esn:calendarCreated', [$this, 'calendarCreated']);
        $this->eventEmitter->on('esn:calendarUpdated', [$this, 'calendarUpdated']);
        $this->eventEmitter->on('esn:calendarDeleted', [$this, 'calendarDeleted']);
        $this->eventEmitter->on('esn:updateSharees', [$this, 'updateSharees']);
    }

    function buildCalendarBody($calendarPath, $type, $calendarProps) {
        $this->body['calendarPath'] = $calendarPath;
        $this->body['type'] = $type;
        $this->body['calendarProps'] = $calendarProps;
    }

    protected function createCalendarMessage($topic) {
        if(!empty($this->body)) {
            $this->messages[] = [
                'topic' => $topic,
                'data' => $this->body
            ];
        }
    }

    function getCalendarProps($node) {
        $properties = [
            "{DAV:}displayname",
            "{urn:ietf:params:xml:ns:caldav}calendar-description" ,
            "{http://apple.com/ns/ical/}calendar-color",
            "{http://apple.com/ns/ical/}apple-order"
        ];

        return $node->getProperties($properties);
    }

    protected function publishCalendarMessage() {
        foreach($this->messages as $message) {
            $path = $message['data']['calendarPath'];
            $this->client->publish($message['topic'], json_encode($message['data']));
        }
    }

    function prepareAndPublishMessage($path, $type, $props, $topic) {
        $this->buildCalendarBody(
            $path,
            $type,
            $props
        );

        $this->createCalendarMessage($topic);
        $this->publishCalendarMessage();
    }

    function calendarCreated($path) {
        $node = $this->server->tree->getNodeForPath($path);
        $props = $this->getCalendarProps($node);

        $this->prepareAndPublishMessage($path, 'created', $props, $this->CALENDAR_TOPICS['CALENDAR_CREATED']);
    }

    function calendarDeleted($path) {
        $this->prepareAndPublishMessage($path, 'deleted', null, $this->CALENDAR_TOPICS['CALENDAR_DELETED']);
    }

    function calendarUpdated($path) {
        $node = $this->server->tree->getNodeForPath($path);
        $props = $this->getCalendarProps($node);

        $this->prepareAndPublishMessage($path, 'updated', $props, $this->CALENDAR_TOPICS['CALENDAR_UPDATED']);
    }

    function updateSharees($calendarInstances) {
        $sharingPlugin = $this->server->getPlugin('sharing');

        foreach($calendarInstances as $instance) {

            if ($instance['type'] == 'delete') {
                $event = $this->CALENDAR_TOPICS['CALENDAR_DELETED'];
                $props = null;
            } else if ($instance['type'] == 'create') {
                $event = $this->CALENDAR_TOPICS['CALENDAR_CREATED'];
                $props = [
                    'access' => $sharingPlugin->accessToRightRse($instance['sharee']->access)
                ];
            } else if ($instance['type'] == 'update') {
                $event = $this->CALENDAR_TOPICS['CALENDAR_UPDATED'];
                $props = [
                    'access' => $sharingPlugin->accessToRightRse($instance['sharee']->access)
                ];
            }

            $principalArray = explode('/', $instance['sharee']->principal);
            $nodeInstance = 'calendars/' . $principalArray[2] . '/' . $instance['uri'];

            $this->buildCalendarBody(
                $nodeInstance,
                $instance['type'],
                $props
            );

            $this->createCalendarMessage($event);
        }

        $this->publishCalendarMessage();
    }
}
