<?php

$xml_str = <<<END
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
	<channel>
		<title>OMR Daily</title>
		<link>https://omr.com/</link>
		<item>
			<title><![CDATA[OMR50 – Das ist das komplette Ranking unserer Marketingmacher des Jahres]]></title>
			<link>https://omr.com/de/omr50-2020/</link>
			<content:encoded><![CDATA[<p>Das gesamte Jahr über beschäftigen wir uns ... meistens jedenfalls&#8230; ... also [&hellip;]</p>]]></content:encoded>
		</item>
		<item>
			<title><![CDATA[Produktverbesserung oder Zugriff auf wertvolle Daten: Warum kauft Facebook Giphy?]]></title>
			<link>https://omr.com/de/facebook-giphy/</link>
			<guid>https://omr.com/de/facebook-giphy/</guid>
			<pubDate>Mon, 18 May 2020 23:08:09 GMT</pubDate>
			<content:encoded><![CDATA[<p>Facebook kauft Giphy: Es ist ... der einzige Grund [&hellip;]</p>]]></content:encoded>
		</item>
	</channel>
</rss>
END;

print \rkphplib\XML::fromMap(\rkphplib\XML::toMap($xml_str));
$xml = new \rkphplib\XML($xml_str);
print "\n\n".$xml;

