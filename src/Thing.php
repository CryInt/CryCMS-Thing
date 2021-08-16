<?php
namespace CryCMS;

use CryCMS\Db;

class Thing
{
    public function test()
    {
	$result = Db::sql("SELECT 1 = 1")->getOne();
	print_r($result);

	print_r('test');
    }
}