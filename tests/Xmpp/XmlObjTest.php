<?php
use PHPUnit\Framework\TestCase;

/**
 * CXMPP_XMLObj: pohon stanza XMPP → string XML, pencarian sub-elemen.
 */
class XmlObjTest extends TestCase {
    /**
     * @return CXMPP_XMLObj
     */
    protected function message() {
        $message = new CXMPP_XMLObj('Message', 'jabber:client', ['To' => 'budi@uji.test', 'type' => 'chat', 'xmlns' => 'abaikan']);
        $body = new CXMPP_XMLObj('body', 'jabber:client', [], 'Halo <dunia> & "kamu"');
        $active = new CXMPP_XMLObj('active', 'http://jabber.org/protocol/chatstates');
        $message->subs[] = $body;
        $message->subs[] = $active;

        return $message;
    }

    public function testNamesAndAttributeKeysAreLowerCased() {
        $message = $this->message();
        $this->assertSame('message', $message->name);
        $this->assertSame('jabber:client', $message->ns);
        $this->assertSame('budi@uji.test', $message->attrs['to']);
        $this->assertSame('chat', $message->attrs['type']);
        $this->assertSame('', $message->data);
    }

    public function testToStringEscapesAttributesAndBodyAndSkipsXmlnsAttribute() {
        $xml = $this->message()->toString();
        $this->assertStringStartsWith("<message xmlns='jabber:client' to='budi@uji.test' type='chat' >", $xml);
        $this->assertStringNotContainsString("xmlns='abaikan'", $xml, 'atribut xmlns dari attrs tidak digandakan');
        $this->assertStringContainsString("<body xmlns='jabber:client' >Halo &lt;dunia&gt; &amp; &quot;kamu&quot;</body>", $xml);
        $this->assertStringContainsString("<active xmlns='http://jabber.org/protocol/chatstates' ></active>", $xml);
        $this->assertStringEndsWith('</message>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml), 'hasilnya XML valid');
    }

    public function testSubLookupByNameAndNamespace() {
        $message = $this->message();
        $this->assertTrue($message->hasSub('body'));
        $this->assertTrue($message->hasSub('active', 'http://jabber.org/protocol/chatstates'));
        $this->assertFalse($message->hasSub('active', 'jabber:client'), 'namespace berbeda');
        $this->assertTrue($message->hasSub('*'));
        $this->assertFalse($message->hasSub('subject'));
        $this->assertSame('Halo <dunia> & "kamu"', $message->sub('body')->data);
        $this->assertNull($message->sub('subject'));
        $this->assertNull($message->sub('body', null, 'lain'));
        $this->assertFalse((new CXMPP_XMLObj('presence'))->hasSub('*'), 'tanpa anak');
    }
}
