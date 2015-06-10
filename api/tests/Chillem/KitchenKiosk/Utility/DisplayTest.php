<?php

namespace KitchenKiosk\Utility;

class DisplayTest extends \PHPUnit_Framework_TestCase {

    private $display;

    public function setUp(){ 
        $this->display = new Display();
    }
    public function tearDown(){ }

    /*
     * Ensure cls() method returns valid array
     */
    public function testcls(){
        $this->assertNull($this->display->cls());
    }

    /*
     * cliFormat() returns false with unknown input
     */
    public function testcliFormat(){
        $this->assertFalse($this->display->cliFormat('foo'));
    }

    /*
     * Test several returned ordinal suffix patterns
     */
    public function testordinalSuffixOne(){
        $this->assertRegExp('/[0-9](?:st|nd|rd|th)/', $this->display->ordinalSuffix(1));
    }

    public function testordinalSuffixTwo(){
        $this->assertRegExp('/[0-9](?:st|nd|rd|th)/', $this->display->ordinalSuffix(2));
    }

    public function testordinalSuffixThree(){
        $this->assertRegExp('/[0-9](?:st|nd|rd|th)/', $this->display->ordinalSuffix(3));
    }

    public function testordinalSuffixFour(){
        $this->assertRegExp('/[0-9](?:st|nd|rd|th)/', $this->display->ordinalSuffix(44));
    }

    public function testordinalSuffixFive(){
        $this->assertRegExp('/[0-9](?:st|nd|rd|th)/', $this->display->ordinalSuffix(157));
    }

    public function testordinalSuffixTen(){
        $this->assertRegExp('/[0-9](?:st|nd|rd|th)/', $this->display->ordinalSuffix(10088));
    }

    /*
     * Randomly generated returned output should be string type
     */
    public function testgenerateStringType(){
        $this->assertInternalType('string', $this->display->generateString(50));
    }

    /*
     * Verify returned string has requested length
     */
    public function testgenerateStringLength(){
        $this->assertRegExp('/^[a-zA-Z0-9]{50}$/', $this->display->generateString(50));
    }
}
