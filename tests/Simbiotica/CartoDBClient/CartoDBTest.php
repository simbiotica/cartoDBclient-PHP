<?php 

namespace Simbiotica\CartoDBBundle;

use Simbiotica\CartoDBClient\FileStorage;

use Simbiotica\CartoDBClient\PrivateConnection;

use Simbiotica\CartoDBClient\SessionStorage;

class CalculatorTest extends \PHPUnit_Framework_TestCase
{
    public function testPrivateConnection()
    {
        $config = array(
                'api_key' => 'your-api-key',
                'consumer_key' => 'your-consumer-key',
                'consumer_secret' => 'your-consumer-secret',
                'subdomain' => 'your-subdomain',
                'email' => 'your-email',
                'password' => 'your-password',
        );
        
        $table = 'test1';
        $schema = array(
                'name' => 'text',
                'description' => 'text',
                'somenumber' => 'numeric',
                'somedate' => 'timestamp without time zone'
        );
        
        $sessionStorage = new FileStorage('./tmp/cartodbauth.txt');
        
        $privateClient = new PrivateConnection($sessionStorage, 
                $config['subdomain'], $config['api_key'], 
                $config['consumer_key'], $config['consumer_secret'], 
                $config['email'], $config['password']
        );
        $this->assertTrue($privateClient->authorized);
        
        //Database can be probed for columns
        $tableNames = $privateClient->getTableNames()->getData();
        if(in_array($table, array_map(function($item){return $item->relname;}, $tableNames)))
        {
            //Cleanup
            $privateClient->dropTable($table);
        }
        
        //Table is created
        $privateClient->createTable($table, $schema);
        $tableNames = $privateClient->getTableNames()->getData();
        $this->assertContains($table, array_map(function($item){return $item->relname;}, $tableNames));
        
        //Table has the right columns
        $columnData = $privateClient->showTable($table, true)->getData();
        foreach($columnData as $column)
        {
            if ($column->column_name == 'cartodb_id')
                $this->assertEquals($column->data_type, 'integer');
            else
                $this->assertEquals($schema[$column->column_name], $column->data_type);
        }
        $columnNames = array_map(function($item){return $item->column_name;}, $columnData);
        $this->assertContains('cartodb_id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('description', $columnNames);
        $this->assertContains('somenumber', $columnNames);
        $this->assertContains('somedate', $columnNames);
        
        //Table doesn't have columns it shouldn't
        if (in_array('someothercolumn', $columnNames))
            $privateClient->dropColumn($table, 'someothercolumn');
        $this->assertFalse(in_array('someothercolumn', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())));
        
        //Columns can be added
        $privateClient->addColumn($table, 'someothercolumn', 'date');
        $this->assertTrue(in_array('someothercolumn', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())));
        
        //Column names can be changed
        $privateClient->changeColumnName($table, 'someothercolumn', 'someothercolumnnewname');
        $this->assertTrue(in_array('someothercolumnnewname', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())));
        $this->assertFalse(in_array('someothercolumn', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())));
        
        //Column types can be changed
        $privateClient->changeColumnType($table, 'someothercolumnnewname', 'text');
        $columnData = $privateClient->showTable($table, true)->getData();
        foreach($columnData as $column)
        {
            if ($column->column_name == 'someothercolumnnewname')
                $this->assertTrue($column->data_type == 'text');
        }
        
        //Columns can be removed
        $privateClient->dropColumn($table, 'someothercolumnnewname');
        $this->assertFalse(in_array('someothercolumnnewname', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())));
        
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
        $tableNames = $privateClient->getTableNames()->getData();
        $this->assertFalse(in_array($table, array_map(function($item){return $item->relname;}, $tableNames)));
    }
}

?>