<?php

namespace ESN\CardDAV;

use Sabre\DAV\ServerPlugin;
use Sabre\VObject\Document;
use Sabre\VObject\ITip\Message;

require_once ESN_TEST_BASE. '/CardDAV/PluginTestBase.php';

class PluginTest extends PluginTestBase {

    protected $userTestId = '5aa1f6639751b711008b4567';

    function setUp() {
        parent::setUp();

        // TODO: the plugin is added in tests/DAV/ServerMock.php hence we do not
        // add it again in this file. We will need to move those mocks and test cases to this file
        // and uncomment 2 lines below
        //$plugin = new Plugin();
        //$this->server->addPlugin($plugin);
    }

    function testPropFindRequestAddressBook() {
        $request = \Sabre\HTTP\Sapi::createFromServerArray(array(
            'REQUEST_METHOD'    => 'PROPFIND',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'       => 'application/json',
            'REQUEST_URI'       => '/addressbooks/54b64eadf6d7d8e41d263e0f/book1.json',
        ));

        $body = '{"properties": ["{DAV:}acl","uri"]}';
        $request->setBody($body);
        $response = $this->request($request);
        $jsonResponse = json_decode($response->getBodyAsString(), true);

        $this->assertEquals(200, $response->status);
        $this->assertEquals(['dav:read', 'dav:write'], $jsonResponse['{DAV:}acl']);
        $this->assertEquals('book1', $jsonResponse['uri']);
    }

    function testPropFindAclOfAddressBook() {
        $request = \Sabre\HTTP\Sapi::createFromServerArray(array(
            'REQUEST_METHOD'    => 'PROPFIND',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'       => 'application/json',
            'REQUEST_URI'       => '/addressbooks/54b64eadf6d7d8e41d263e0f/book1.json',
        ));

        $body = '{"properties": ["acl"]}';
        $request->setBody($body);
        $response = $this->request($request);
        $jsonResponse = json_decode($response->getBodyAsString(), true);

        $this->assertEquals(200, $response->status);
        $this->assertEquals([
            [
                'privilege' => '{DAV:}all',
                'principal' => 'principals/users/54b64eadf6d7d8e41d263e0f',
                'protected' => true
            ]
        ], $jsonResponse['acl']);
    }

    function testDeleteDefaultAddressBook() {
        $request = \Sabre\HTTP\Sapi::createFromServerArray(array(
            'REQUEST_METHOD'    => 'DELETE',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'       => 'application/json',
            'REQUEST_URI'       => '/addressbooks/54b64eadf6d7d8e41d263e0f/contacts.json',
        ));

        $contactsAddressBook = array(
            'uri' => 'contacts',
            'principaluri' => 'principals/users/54b64eadf6d7d8e41d263e0f',
        );

        $this->carddavBackend->createAddressBook($contactsAddressBook['principaluri'],
            $contactsAddressBook['uri'],
            [
                '{DAV:}displayname' => 'contacts',
                '{urn:ietf:params:xml:ns:carddav}addressbook-description' => 'Contacts description'
            ]);

        $addressbooks = $this->carddavBackend->getAddressBooksForUser($this->carddavAddressBook['principaluri']);
        $this->assertCount(2, $addressbooks);

        $response = $this->request($request);
        $this->assertEquals(403, $response->status);

        $addressbooks = $this->carddavBackend->getAddressBooksForUser($this->carddavAddressBook['principaluri']);
        $this->assertCount(2, $addressbooks);
    }

    function testDeleteCollectedAddressBook() {
        $request = \Sabre\HTTP\Sapi::createFromServerArray(array(
            'REQUEST_METHOD'    => 'DELETE',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'       => 'application/json',
            'REQUEST_URI'       => '/addressbooks/54b64eadf6d7d8e41d263e0f/collected.json',
        ));

        $collectedAddressBook = array(
            'uri' => 'collected',
            'principaluri' => 'principals/users/54b64eadf6d7d8e41d263e0f',
        );

        $this->carddavBackend->createAddressBook($collectedAddressBook['principaluri'],
            $collectedAddressBook['uri'],
            [
                '{DAV:}displayname' => 'collected',
                '{urn:ietf:params:xml:ns:carddav}addressbook-description' => 'Collected description'
            ]);

        $addressbooks = $this->carddavBackend->getAddressBooksForUser($this->carddavAddressBook['principaluri']);
        $this->assertCount(2, $addressbooks);

        $response = $this->request($request);
        $this->assertEquals(403, $response->status);

        $addressbooks = $this->carddavBackend->getAddressBooksForUser($this->carddavAddressBook['principaluri']);
        $this->assertCount(2, $addressbooks);
    }

    function testDeleteAddressBook() {
        $this->esndb->users->insert([
            '_id'       => new \MongoId($this->userTestId),
            'firstname' => 'user',
            'lastname'  => 'test',
            'accounts'  => [
                [
                    'type' => 'email',
                    'emails' => [
                      'usertest@mail.com'
                    ]
                ]
            ]
        ]);
        $this->carddavBackend->createSubscription(
            'principals/users/' . $this->userTestId,
            'book2',
            [
                '{DAV:}displayname' => 'Book 1',
                '{http://open-paas.org/contacts}source' => new \Sabre\DAV\Xml\Property\Href('addressbooks/54b64eadf6d7d8e41d263e0f/book1', false)
            ]
        );

        $subscriptions = $this->carddavBackend->getSubscriptionsForUser('principals/users/'. $this->userTestId);
        $this->assertCount(1, $subscriptions);

        $request = \Sabre\HTTP\Sapi::createFromServerArray(array(
            'REQUEST_METHOD'    => 'DELETE',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'       => 'application/json',
            'REQUEST_URI'       => '/addressbooks/54b64eadf6d7d8e41d263e0f/book1.json',
        ));

        $addressbooks = $this->carddavBackend->getAddressBooksForUser($this->carddavAddressBook['principaluri']);
        $this->assertCount(1, $addressbooks);

        $response = $this->request($request);
        $this->assertEquals(204, $response->status);

        $addressbooks = $this->carddavBackend->getAddressBooksForUser($this->carddavAddressBook['principaluri']);
        $this->assertCount(0, $addressbooks);

        $subscriptions = $this->carddavBackend->getSubscriptionsForUser('principals/users/'. $this->userTestId);
        $this->assertCount(0, $subscriptions);
    }
}
