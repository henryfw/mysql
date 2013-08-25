mysql
=====

Simple PHP MySQL DB wrapper. All data passed in via array will be escaped automatically. Great for just grabbing one row or one column without having to write a lot of code.

Usage:

    $connection = array(
        "host" => "localhost",
        "user" => "root",
        "pass" => "",
        "db" => "test",
    );
    $db = DB::get_link( $connection );
    
    
    // select one col
    $data = $db->get("test_table", "*", array( "id" => 1 ) );
    
    
    // select one row
    $data = $db->get_row("test_table", "id, name", array( "id" => 1, "name" => "henry" ) );
    
    
    // select all table
    $data = $db->get_all_rows("test_table", "*", " `id` = 1" );
    
    // update
    $success = $db->update("test_table", array("col_name" => "new_value"), array( "id" => 1 ) );
    
    // insert
    $insert_id_or_success = $db->insert("test_table", array("col_name" => "new_value") );
    
    
    // delete
    $success = $db->delete("test_table", array( "id" => 1 ), 1 ); // defaults to limit 1
    
    // query for custom query
    $result = $db->query("SELECT test_table.* FROM test_table LEFT JOIN ON table2 WHERE test_table.id = table2.id ORDER BY test_table.id ");
    if ($result->num_rows) {
        while($row = $result->fetch_assoc() ) {
            // do something
        }
    }
    
    
    
