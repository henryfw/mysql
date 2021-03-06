mysql
=====

Simple PHP MySQL DB wrapper using mysqli. All data passed in via array will be escaped automatically. Great for just grabbing one row or one column without having to write a lot of code.

Usage:

    $connection = array(
        "host" => "localhost",
        "user" => "root",
        "pass" => "",
        "db" => "test",
    );
    $db = DB::get_link( $connection );
    
    
    // select one col
    $data = $db->get("test_table", "id", array( "id" => 1 ) );
    
    
    // select one row
    $data = $db->get_row("test_table", "*", array( "id" => 1, "name" => "henry" ) );
    
    
    // select all table
    $data = $db->get_all_rows("test_table", "*", " `id` = 1" );
    
    // update
    $success = $db->update("test_table", array("col_name" => "new_value", "c2" => "1"), array( "id" => 1 ) );
    
    // insert
    $insert_id_or_success = $db->insert("test_table", array("col_name" => "new_value") );
    
    // insert with on duplicate key
    $success = $db->insert("test_table", array("col_name", "new_value"), array("id" => 1), array("col_name", "new_value_on_dup_key"));
    
    
    // delete
    $success = $db->delete("test_table", array( "id" => 1 ), 1 ); // defaults to limit 1
    
    // query for custom query
    $result = $db->query("SELECT test_table.* FROM test_table LEFT JOIN table2 ON id WHERE table2.col_val = 1 ");
    if ($result->num_rows) {
        while($row = $result->fetch_assoc() ) {
            // do something
        }
    }
    
    
    // to get all queries ran using a log
    $db->logging = true; // set before running any methods
    print_r($db->logs); // print at end of calls
    
=====
MIT License
    
