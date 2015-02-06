<?php
/*
 * This file is part of the Eko\FeedBundle Symfony bundle.
 *
 * (c) Vincent Composieux <vincent.composieux@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eko\FeedBundle\Tests\Formatter;

use Eko\FeedBundle\Feed\FeedManager;
use Eko\FeedBundle\Field\Channel\ChannelField;
use Eko\FeedBundle\Field\Channel\GroupChannelField;
use Eko\FeedBundle\Field\Item\GroupItemField;
use Eko\FeedBundle\Field\Item\ItemField;
use Eko\FeedBundle\Field\Item\MediaItemField;
use Eko\FeedBundle\Formatter\AtomFormatter;
use Eko\FeedBundle\Formatter\RssFormatter;
use Eko\FeedBundle\Tests\Entity\Writer\FakeItemInterfaceEntity;
use Eko\FeedBundle\Tests\Entity\Writer\FakeRoutedItemInterfaceEntity;

/**
 * AtomFormatterTest
 *
 * This is the Atom formatter test class
 *
 * @author Vincent Composieux <vincent.composieux@gmail.com>
 */
class AtomFormatterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FeedManager $manager A feed manager instance
     */
    protected $manager;

    /**
     * Sets up elements used in test case
     */
    public function setUp() {
        $config = array(
            'feeds' => array(
                'article' => array(
                    'title'       => 'My articles/posts',
                    'description' => 'Latests articles',
                    'link'        => 'http://github.com/eko/FeedBundle',
                    'encoding'    => 'utf-8',
                    'author'      => 'Vincent Composieux'
                )
            )
        );

        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');

        $formatters = array(
            'rss'  => new RssFormatter($translator, 'test'),
            'atom' => new AtomFormatter($translator, 'test'),
        );

        $this->manager = new FeedManager($this->getMockRouter(), $config, $formatters);
    }

    /**
     * Check if exception is an \InvalidArgumentException is thrown
     * when 'author' config parameter is not set or empty
     */
    public function testAuthorEmptyException()
    {
        $config = array(
            'feeds' => array(
                'article' => array(
                    'title'       => 'My title',
                    'description' => 'My description',
                    'link'        => 'http://github.com/eko/FeedBundle',
                    'encoding'    => 'utf-8',
                    'author'      => ''
                )
            )
        );

        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');

        $formatters = array(
            'rss'  => new RssFormatter($translator, 'test'),
            'atom' => new AtomFormatter($translator, 'test'),
        );

        $manager = new FeedManager($this->getMockRouter(), $config, $formatters);

        $feed = $manager->get('article');

        $this->setExpectedException(
            'InvalidArgumentException',
            'Atom formatter requires an "author" parameter in configuration.'
        );

        $feed->set('author', null);
        $feed->render('atom');
    }

    /**
     * Check if RSS formatter output a valid XML
     */
    public function testRenderValidXML()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());

        $output = $feed->render('atom');

        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->loadXML($output);

        $this->assertEquals(0, count(libxml_get_errors()));
        $this->assertContains('<feed xmlns="http://www.w3.org/2005/Atom">', $output);
    }

    /**
     * Check if RSS formatter output item
     */
    public function testRenderItem()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());

        $output = $feed->render('atom');

        $this->assertContains('<title><![CDATA[Fake title]]></title>', $output);
        $this->assertContains('<summary><![CDATA[Fake description or content]]></summary>', $output);
        $this->assertContains('<link href="http://github.com/eko/FeedBundle/article/fake/url"/>', $output);
    }

    /**
     * Check if a custom channel field is properly rendered
     */
    public function testAddCustomChannelField()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());
        $feed->addChannelField(new ChannelField('fake_channel_custom', 'My fake value'));

        $output = $feed->render('atom');

        $this->assertContains('<fake_channel_custom>My fake value</fake_channel_custom>', $output);
    }

    /**
     * Check if a custom item field is properly rendered with ItemInterface
     */
    public function testAddCustomItemFieldWithItemInterface()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());
        $feed->addItemField(new ItemField('fake_custom', 'getFeedItemCustom'));

        $output = $feed->render('atom');

        $this->assertContains('<fake_custom>My custom field</fake_custom>', $output);
    }

    /**
     * Check if a custom media item field is properly rendered with ItemInterface
     */
    public function testAddCustomMediaItemFieldWithItemInterface()
    {
        $fakeEntityWithMedias = new FakeItemInterfaceEntity();
        $fakeEntityWithMedias->setFeedMediaItem(array(
            'type'   => 'image/jpeg',
            'length' => 500,
            'value'  => 'http://website.com/image.jpg'
        ));

        $fakeEntityNoMedias = new FakeItemInterfaceEntity();

        $feed = $this->manager->get('article');
        $feed->add($fakeEntityWithMedias);
        $feed->add($fakeEntityNoMedias);
        $feed->addItemField(new MediaItemField('getFeedMediaItem'));

        $output = $feed->render('atom');

        $this->assertContains('<link rel="enclosure" href="http://website.com/image.jpg" type="image/jpeg" length="500"/>', $output);
    }

    /**
     * Check if a custom group media items field is properly rendered with ItemInterface
     */
    public function testAddCustomGroupMediaItemsFieldsWithItemInterface()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());
        $feed->addItemField(new GroupItemField(
            'images',
            new MediaItemField('getFeedMediaMultipleItems'))
        );

        $output = $feed->render('atom');

        $this->assertContains('<images>', $output);
        $this->assertContains('<link rel="enclosure" href="http://website.com/image.jpg" type="image/jpeg" length="500"/>', $output);
        $this->assertContains('<link rel="enclosure" href="http://website.com/image2.png" type="image/png" length="600"/>', $output);
        $this->assertContains('</images>', $output);
    }

    /**
     * Check if a custom group item field is properly rendered with ItemInterface
     */
    public function testAddCustomGroupItemFieldWithItemInterface()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());
        $feed->addItemField(new GroupItemField(
            'categories',
            new ItemField('category', 'getFeedCategoriesCustom'))
        );

        $output = $feed->render('atom');

        $this->assertContains('<categories>', $output);
        $this->assertContains('<category>category 1</category>', $output);
        $this->assertContains('<category>category 2</category>', $output);
        $this->assertContains('<category>category 3</category>', $output);
        $this->assertContains('</categories>', $output);
    }

    /**
     * Check if a custom group item field with attributes is properly rendered
     */
    public function testAddCustomGroupItemFieldWithAttributes()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());
        $feed->addItemField(
            new GroupItemField(
                'categories',
                new ItemField('category', 'getFeedCategoriesCustom', array(), array('category-type' => 'test')),
                array('is-it-test' => 'yes')
            )
        );

        $output = $feed->render('atom');

        $this->assertContains('<categories is-it-test="yes">', $output);
        $this->assertContains('<category category-type="test">category 1</category>', $output);
        $this->assertContains('<category category-type="test">category 2</category>', $output);
        $this->assertContains('<category category-type="test">category 3</category>', $output);
        $this->assertContains('</categories>', $output);
    }

    /**
     * Check if a custom group item field with attributes from method is properly rendered
     */
    public function testAddCustomGroupItemFieldWithAttributesFromMethod()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());
        $feed->addItemField(
            new GroupItemField(
                'categories',
                new ItemField('category', 'getFeedCategoriesCustom', array(), array('getItemKeyAttribute' => 'getItemValueAttribute')),
                array('getGroupKeyAttribute' => 'getGroupValueAttribute')
            )
        );

        $output = $feed->render('atom');

        $this->assertContains('<categories my-group-key-attribute="my-group-value-attribute">', $output);
        $this->assertContains('<category my-item-key-attribute="my-item-value-attribute">category 1</category>', $output);
        $this->assertContains('<category my-item-key-attribute="my-item-value-attribute">category 2</category>', $output);
        $this->assertContains('<category my-item-key-attribute="my-item-value-attribute">category 3</category>', $output);
        $this->assertContains('</categories>', $output);
    }

    /**
     * Check if a custom group channel field is properly rendered with GroupFieldInterface
     */
    public function testAddCustomGroupChannelFieldWithItemInterface()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());
        $feed->addChannelField(
            new GroupChannelField('image', array(
                new ChannelField('name', 'My test image'),
                new ChannelField('url', 'http://www.example.com/image.jpg')
            ))
        );

        $output = $feed->render('atom');

        $this->assertContains('<image>', $output);
        $this->assertContains('<name>My test image</name>', $output);
        $this->assertContains('<url>http://www.example.com/image.jpg</url>', $output);
        $this->assertContains('</image>', $output);
    }

    /**
     * Check if a custom group channel field is properly rendered with attributes
     */
    public function testAddCustomGroupChannelFieldWithAttributes()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());
        $feed->addChannelField(
            new GroupChannelField('image', array(
                new ChannelField('name', 'My test image', array(), array('name-attribute' => 'test')),
                new ChannelField('url', 'http://www.example.com/image.jpg', array(), array('url-attribute' => 'test'))
            ), array('image-attribute' => 'test'))
        );

        $output = $feed->render('atom');

        $this->assertContains('<image image-attribute="test">', $output);
        $this->assertContains('<name name-attribute="test">My test image</name>', $output);
        $this->assertContains('<url url-attribute="test">http://www.example.com/image.jpg</url>', $output);
        $this->assertContains('</image>', $output);
    }

    /**
     * Check if a custom group item field with multiple item fields is properly rendered with ItemInterface
     */
    public function testAddCustomGroupMultipleItemFieldWithItemInterface()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeItemInterfaceEntity());
        $feed->addItemField(new GroupItemField('author', array(
            new ItemField('name', 'getFeedItemAuthorName', array('cdata' => true)),
            new ItemField('email', 'getFeedItemAuthorEmail'),
        )));

        $output = $feed->render('atom');

        $this->assertContains(<<<EOF
    <author>
      <name><![CDATA[John Doe]]></name>
      <email>john.doe@example.org</email>
    </author>
EOF
            , $output);
    }

    /**
     * Check if a custom item field is properly rendered with RoutedItemInterface
     */
    public function testAddCustomItemFieldWithRoutedItemInterface()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeRoutedItemInterfaceEntity());
        $feed->addItemField(new ItemField('fake_custom', 'getFeedItemCustom'));

        $output = $feed->render('atom');

        $this->assertContains('<fake_custom>My custom field</fake_custom>', $output);
    }

    /**
     * Check if anchors are really appended to generated url of RouterItemInterface
     */
    public function testAnchorIsAppendedToLinkWithRoutedItemInterface()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeRoutedItemInterfaceEntity());

        $output = $feed->render('atom');
        $this->assertContains('<link href="http://github.com/eko/FeedBundle/article/fake/url#fake-anchor"/>', $output);
    }

    /**
     * Check if an exception is thrown when trying to render a non-existant method with RoutedItemInterface
     */
    public function testNonExistantCustomItemFieldWithRoutedItemInterface()
    {
        $feed = $this->manager->get('article');
        $feed->add(new FakeRoutedItemInterfaceEntity());
        $feed->addItemField(new ItemField('fake_custom', 'getFeedDoNotExistsItemCustomMethod'));

        $this->setExpectedException(
            'InvalidArgumentException',
            'Method "getFeedDoNotExistsItemCustomMethod" should be defined in your entity.'
        );

        $feed->render('atom');
    }

    /**
     * Check if values are well translated with "translatable" option
     */
    public function testTranslatableValue()
    {
        $config = array(
            'feeds' => array(
                'article' => array(
                    'title'       => 'My title',
                    'description' => 'My description',
                    'link'        => 'http://github.com/eko/FeedBundle',
                    'encoding'    => 'utf-8',
                    'author'      => 'Vincent'
                )
            )
        );

        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $translator->expects($this->any())->method('trans')->will($this->returnValue('translatable-value'));

        $formatters = array('atom' => new AtomFormatter($translator, 'test'));

        $manager = new FeedManager($this->getMockRouter(), $config, $formatters);

        $feed = $manager->get('article');
        $feed->add(new FakeRoutedItemInterfaceEntity());
        $feed->addItemField(new ItemField('fake_custom', 'getFeedItemCustom', array(
            'translatable' => true
        )));

        $output = $feed->render('atom');
        $this->assertContains('<fake_custom>translatable-value</fake_custom>', $output);
    }

    /**
     * Returns RouterInterface mock
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockRouter()
    {
        $mockRouter = $this->getMock('\Symfony\Bundle\FrameworkBundle\Routing\Router', array(), array(), '', false);

        $mockRouter->expects($this->any())
            ->method('generate')
            ->will($this->returnValue('http://github.com/eko/FeedBundle/article/fake/url'));

        return $mockRouter;
    }
}
