<?php
/**
 * Class ConstructionPlanTest
 *
 * @package Wp_Starter_Plugin
 */

namespace WPCustomTables\Tests;

/**
 * Construction plan test case.
 */
require_once dirname(__DIR__) . '/lib/WPCustomTables/QueryBuilder.php';

use Mockery;
// use Brain\Monkey\Functions;
use WPCustomTables\QueryBuilder;

class QueryBuilderTest extends TestCase
{
  // /**
  //  * @runInSeparateProcess
  //  * @preserveGlobalState disabled
  //  */
  //   public function testRegisterFieldGroup()
  //   {
  //       $config = 'this is a config';
  //       $fieldGroup = 'this is a field group';
  //       $returnValue = 'this is a return value';
  //       Mockery::mock('alias:ACFComposer\ResolveConfig')
  //           ->shouldReceive('forFieldGroup')
  //           ->with($config)
  //           ->once()
  //           ->andReturn($fieldGroup);
  //       Functions::expect('acf_add_local_field_group')
  //           ->with($fieldGroup)
  //           ->once()
  //           ->andReturn($returnValue);
  //       $output = ACFComposer::registerFieldGroup($config);
  //       $this->assertEquals($returnValue, $output);
  //   }
    public function testSelectColumnFromTable()
    {
        $actual = QueryBuilder::table('foo')->select('bar')->buildQuery();
        $expected = 'SELECT `bar` FROM `foo`';

        $this->assertEquals($expected, $actual);
    }

    public function testNestedWhere()
    {
      $actual = QueryBuilder::table('foo')->select('bar')->where(function ($query) {
          $query->where('bar', 'baz')->orWhere('bar', 'bat');
      })->buildQuery();
      $expected = 'SELECT `bar` FROM `foo` WHERE (`bar` = \'baz\' OR `bar` = \'bat\')';

      $this->assertEquals($expected, $actual);
    }
}
