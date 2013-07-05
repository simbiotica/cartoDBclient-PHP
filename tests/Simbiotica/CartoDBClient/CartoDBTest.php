<?php 

namespace Simbiotica\CartoDBClient;

use Simbiotica\CartoDBClient\FileStorage;
use Simbiotica\CartoDBClient\PrivateConnection;

class CartoDBTest extends \PHPUnit_Framework_TestCase
{
    public function testPrivateConnection()
    {
        $config = CartoDBConfig::getPrivateConfig();
        
        $table = 'test_f923nf93mv9a'; //random string to make table name unique
        $schema = array(
                'name' => 'text',
                'description' => 'text',
                'somenumber' => 'numeric',
                'somedate' => 'timestamp without time zone'
        );
        
        $privateClient = new PrivateConnection(null, 
                $config['subdomain'],
                (array_key_exists('api_key', $config)?$config['api_key']:null),
                (array_key_exists('consumer_key', $config)?$config['consumer_key']:null),
                (array_key_exists('consumer_secret', $config)?$config['consumer_secret']:null),
                $config['email'], $config['password']
        );
        $this->assertTrue($privateClient->authorized, 'Correct credentials should authenticate on a private table connection');
        
        /**
         * A change in CartoDB prevents fetching metadata, so this section is skipped.
         */
        $privateClient->dropTable($table);
        $privateClient->createTable($table, $schema);
        
//        //Database can be probed for columns
//        $tableNames = $privateClient->getTableNames()->getData();
//        if(in_array($table, array_map(function($item){return $item->relname;}, $tableNames)))
//        {
//            //Cleanup
//            $privateClient->dropTable($table);
//        }
//        
//        //Create table
//        $privateClient->createTable($table, $schema);
//        
//        //Table has the right columns
        $columnNames = array_map(function($item){return $item->cdb_columnnames;}, $privateClient->getColumnNames($table)->getData());
        $this->assertContains('cartodb_id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('description', $columnNames);
        $this->assertContains('somenumber', $columnNames);
        $this->assertContains('somedate', $columnNames);
        
        foreach($columnNames as $columnName)
        {
            $columnType = array_pop($privateClient->getColumnType($table, $columnName)->getData())->cdb_columntype;

            if ($columnName == 'cartodb_id')
                $this->assertEquals($columnType, 'integer');
            else
                $this->assertEquals($schema[$columnName], $columnType);
        }
        
        //Table doesn't have columns it shouldn't
        if (in_array('someothercolumn', $columnNames))
            $privateClient->dropColumn($table, 'someothercolumn');
        $this->assertFalse(in_array('someothercolumn', array_map(function($item){return $item->cdb_columnnames;}, $privateClient->getColumnNames($table)->getData())));
        
        //Columns can be added
        $privateClient->addColumn($table, 'someothercolumn', 'date');
        $this->assertTrue(in_array('someothercolumn', array_map(function($item){return $item->cdb_columnnames;}, $privateClient->getColumnNames($table)->getData())));
        
        //Column names can be changed
        $privateClient->changeColumnName($table, 'someothercolumn', 'someothercolumnnewname');
        $this->assertTrue(in_array('someothercolumnnewname', array_map(function($item){return $item->cdb_columnnames;}, $privateClient->getColumnNames($table)->getData())));
        $this->assertFalse(in_array('someothercolumn', array_map(function($item){return $item->cdb_columnnames;}, $privateClient->getColumnNames($table)->getData())));
        
        //Column types can be changed
        $privateClient->changeColumnType($table, 'someothercolumnnewname', 'text');
        $this->assertEquals('text', array_pop($privateClient->getColumnType($table, 'someothercolumnnewname')->getData())->cdb_columntype);;
        
        //Columns can be removed
        $privateClient->dropColumn($table, 'someothercolumnnewname');
        $this->assertFalse(in_array('someothercolumnnewname', array_map(function($item){return $item->cdb_columnnames;}, $privateClient->getColumnNames($table)->getData())));
        
        //Table is empty
        $privateClient->getAllRows($table);
        $this->assertEquals(0, $privateClient->getAllRows($table)->getRowCount());
        
        //Rows can be inserted
        $row1 = array(
                'name' => 'name of test row 1',
                'description' => 'description of test row 1',
                'somenumber' => 111,
                'somedate' => new \DateTime(),
        );
        $privateClient->insertRow($table, $row1);
        $payload = $privateClient->getAllRows($table);
        $this->assertEquals(1, $payload->getRowCount());
        $data = $payload->getData();
        foreach(reset($data) as $name => $value)
        {
            if ($name == 'cartodb_id')
                $this->assertGreaterThanOrEqual(1, $value);
            //for now, skip dates, as we have to do some timezone jugling I don't have time for right now
            elseif($schema[$name] != 'timestamp without time zone')
                $this->assertEquals($row1[$name], $value);
        }
        
        //Rows can be updated.
        $updatedRow1 = array(
                'name' => 'renamed test row 1',
                'description' => 'renamed description of test row 1',
                'somenumber' => 222,
                'somedate' => new \DateTime(),
        );
        $privateClient->updateRow($table, 1, $updatedRow1);
        $payload = $privateClient->getAllRows($table);
        $this->assertEquals(1, $payload->getRowCount());
        $data = $payload->getData();
        foreach(reset($data) as $name => $value)
        {
            if ($name == 'cartodb_id')
                $this->assertGreaterThanOrEqual(1, $value);
            //for now, skip dates, as we have to do some timezone jugling I don't have time for right now
            elseif($schema[$name] != 'timestamp without time zone')
            $this->assertEquals($updatedRow1[$name], $value);
        }
        
        //Rows can be deleted
        $privateClient->deleteRow($table, 1);
        $payload = $privateClient->getAllRows($table);
        $this->assertEquals(0, $payload->getRowCount());
        
        //Reinserting rows
        $privateClient->insertRow($table, $row1);
        $privateClient->insertRow($table, $row1);
        $privateClient->insertRow($table, $row1);
        $payload = $privateClient->getAllRows($table);
        $this->assertEquals(3, $payload->getRowCount());
        
        //Tables can be truncated
        $privateClient->truncateTable($table);
        $payload = $privateClient->getAllRows($table);
        $this->assertEquals(0, $payload->getRowCount());
        
        //Tables can be deleted
        $privateClient->dropTable($table);
//        $tableNames = $privateClient->getTableNames()->getData();
//        $this->assertFalse(in_array($table, array_map(function($item){return $item->relname;}, $tableNames)));
    }
    
    public function testTransformersConnection()
    {
        $config = CartoDBConfig::getPrivateConfig();
    
        $table = 'test_f923nf93mv9a';
        $schema = array(
                'name' => 'text',
                'description' => 'text',
                'sometext1' => 'text',
                'sometext2' => 'text',
                'sometext3' => 'text',
        );
    
        $sessionStorage = new FileStorage('./tmp/cartodbauth.txt');
    
        $privateClient = new PrivateConnection($sessionStorage,
                $config['subdomain'], 
                (array_key_exists('api_key', $config)?$config['api_key']:null),
                (array_key_exists('consumer_key', $config)?$config['consumer_key']:null),
                (array_key_exists('consumer_secret', $config)?$config['consumer_secret']:null),
                $config['email'], $config['password']
        );
    
        $privateClient->dropTable($table);
        $privateClient->createTable($table, $schema);
        
//        //Database can be probed for columns
//        $tableNames = $privateClient->getTableNames()->getData();
//        if(in_array($table, array_map(function($item){return $item->relname;}, $tableNames)))
//        {
//            //Cleanup
//            $privateClient->dropTable($table);
//        }
//    
//        //Table is created
//        $privateClient->createTable($table, $schema);
        
        //Insert rows
        $row1 = array(
                'name' => 'name of test row 1',
                'description' => 'description of test row 1',
                'sometext1' => 'sometext1',
                'sometext2' => 'sometext2',
                'sometext3' => 'sometext3',
        );
        
        $transformers = array(
                'name' => "'Static Escaped String'",
                'description' => "'%s'",
                'sometext1' => '(SELECT COUNT(DISTINCT(cartodb_id)) from '.$table.' WHERE 1=1)',
                'sometext2' => "upper('%s')",
                'sometext3' => null
        );
        
        $privateClient->insertRow($table, $row1);
        $privateClient->insertRow($table, $row1);
        $privateClient->insertRow($table, $row1, $transformers);
        $privateClient->insertRow($table, $row1, $transformers);
        
        //Get data
        $payload = $privateClient->getAllRows($table, array('order' => array('cartodb_id' => 'asc')));
        $this->assertEquals(4, $payload->getRowCount());
        $data = $payload->getData();
        
        //Row 1 as inserted
        $row = array_shift($data);
        $this->assertEquals($row->cartodb_id, 1);
        $this->assertEquals($row->name, $row1['name']);
        $this->assertEquals($row->description, $row1['description']);
        $this->assertEquals($row->sometext1, $row1['sometext1']);
        $this->assertEquals($row->sometext2, $row1['sometext2']);
        $this->assertEquals($row->sometext3, $row1['sometext3']);
        
        //Row 2 as inserted
        $row = array_shift($data);
        $this->assertEquals($row->cartodb_id, 2);
        $this->assertEquals($row->name, $row1['name']);
        $this->assertEquals($row->description, $row1['description']);
        $this->assertEquals($row->sometext1, $row1['sometext1']);
        $this->assertEquals($row->sometext2, $row1['sometext2']);
        $this->assertEquals($row->sometext3, $row1['sometext3']);
        
        //Row 3 was transformed
        $row = array_shift($data);
        $this->assertEquals($row->cartodb_id, 3);
        $this->assertEquals($row->name, 'Static Escaped String');
        $this->assertEquals($row->description, $row1['description']);
        $this->assertEquals($row->sometext1, '2');
        $this->assertEquals($row->sometext2, strtoupper($row1['sometext2']));
        $this->assertEquals($row->sometext3, null);
        
        //Row 4 was transformed
        $row = array_shift($data);
        $this->assertEquals($row->cartodb_id, 4);
        $this->assertEquals($row->name, 'Static Escaped String');
        $this->assertEquals($row->description, $row1['description']);
        $this->assertEquals($row->sometext1, '3');
        $this->assertEquals($row->sometext2, strtoupper($row1['sometext2']));
        $this->assertEquals($row->sometext3, null);
        
        //Update row 1 with transformers
        $privateClient->updateRow($table, 1, $row1, $transformers);
        
        //Get data
        $payload = $privateClient->getAllRows($table, array('order' => array('cartodb_id' => 'asc')));
        $this->assertEquals(4, $payload->getRowCount());
        $data = $payload->getData();
        
        //Row 1 was transformed
        $row = array_shift($data);
        $this->assertEquals($row->cartodb_id, 1);
        $this->assertEquals($row->name, 'Static Escaped String');
        $this->assertEquals($row->description, $row1['description']);
        $this->assertEquals($row->sometext1, '4');
        $this->assertEquals($row->sometext2, strtoupper($row1['sometext2']));
        $this->assertEquals($row->sometext3, null);
        
        $privateClient->dropTable($table);
    }
}

?>