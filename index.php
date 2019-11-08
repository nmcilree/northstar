<?php

header("Access-Control-Allow-Origin: *");
include("../config.php");

// This should only run in qa or loca
$stages = array('nmc','qa');
if(!in_array($CFG->stage, $stages)){
  die();
}


//////////////////////////////////////////////////////// utils ////////////////////////////////////////////////////////


function update_board_version($board_id){
    $query = "UPDATE boards SET version = version + 1 WHERE id = $board_id";
    $result = pg_query($query) or die('Query failed: ' . pg_last_error());
}



//////////////////////////////////////////////////////// Migrations ////////////////////////////////////////////////////////

# Delete tiles table
if(isset($_GET['delete_tiles'])){
    $query = "DELETE FROM tiles WHERE id >= 1;";
    $result = pg_query($query) or die('Query failed: ' . pg_last_error());
}


# Delete tiles table
if(isset($_GET['delete_boards'])){
    $query = "DELETE FROM boards WHERE id >= 1;";
    $result = pg_query($query) or die('Query failed: ' . pg_last_error());
}

# Boards
$query = "SELECT id FROM boards";
$result = pg_query($query);
if(empty($result)) {
    $query = "CREATE TABLE IF NOT EXISTS boards (
              id SERIAL PRIMARY KEY,
              board_name CHARACTER VARYING(255) NOT NULL,
              top_label CHARACTER VARYING(255) NULL,
              right_label CHARACTER VARYING(255) NULL,
              left_label CHARACTER VARYING(255) NULL,
              bottom_label CHARACTER VARYING(255) NULL,
              version integer DEFAULT 1
            )";
    
    $result = pg_query($query) or die('Query failed: ' . pg_last_error());
}    

# Tiles
$query = "CREATE TABLE IF NOT EXISTS tiles (
            id SERIAL PRIMARY KEY,
            title CHARACTER VARYING(255) NOT NULL,
            content CHARACTER VARYING(255) NULL,
            left_pos CHARACTER VARYING(255) NULL,
            top_pos CHARACTER VARYING(255) NULL,
            board_id CHARACTER VARYING(255) NULL,
            version integer DEFAULT 1
          )";
  
$result = pg_query($query) or die('Query failed: ' . pg_last_error());

//////////////////////////////////////////////////////// Init ////////////////////////////////////////////////////////

$board_id = 0;

# Connecting, selecting database
$dbconn = pg_connect("host=".$CFG->dbhost." dbname=".$CFG->dbname." user=".$CFG->dbuser." password=".$CFG->dbpass." port=".$CFG->dboptions['dbport'])
    or die('Could not connect sucker: ' . pg_last_error());

# Get project boards
$query = "SELECT * FROM boards";
$result = pg_query($query) or die('Query failed: ' . pg_last_error());
$boards = array();
while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    $boards[$line['id']] = $line['board_name'];
}

# See if a board has been selected
if(isset($_GET['board_id'])){
  
  # Load the board
  $board_id = $_GET['board_id'];
  $query = "SELECT * FROM boards where id = $board_id";
  $result = pg_query($query) or die('Query failed: ' . pg_last_error());
  while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    $board_id = $line['id'];
    $board_name = $line['board_name'];
    $board_version = $line['version'];
    $top_label = $line['top_label'];
    $bottom_label = $line['bottom_label'];
    $left_label = $line['left_label'];
    $right_label = $line['right_label'];
  }  


  # Load any tiles
  $tiles = array();
  $query = "SELECT * FROM tiles where board_id = '$board_id'";
  $result = pg_query($query) or die('Query failed: ' . pg_last_error());
  while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    array_push($tiles, $line);
  }

}


//////////////////////////////////////////////////////// Controller ////////////////////////////////////////////////////////
if(isset($_POST['action'])){


  # Add a new board
  if($_POST['action'] == 'add_board' && $_POST['board_name'] != ''){
      
      $board_name = pg_escape_string($_POST['board_name']);
      $top_label = pg_escape_string($_POST['top_label']);
      $bottom_label = pg_escape_string($_POST['bottom_label']);
      $right_label = pg_escape_string($_POST['right_label']);
      $left_label = pg_escape_string($_POST['left_label']);
      
      $query = "INSERT INTO boards (board_name, top_label, bottom_label, right_label, left_label) VALUES ('$board_name', '$top_label', '$bottom_label', '$right_label', '$left_label');";
      $result = pg_query($query) or die('Query failed: ' . pg_last_error());
      header('location: index.php');
  }


  # Add a new tile
  if($_POST['action'] == 'add_tile' && $_POST['tile_title'] != '' && isset($_POST['board_id'])){
      
      $tile_title = pg_escape_string(htmlspecialchars($_POST['tile_title']));
      $tile_content = pg_escape_string(htmlspecialchars($_POST['tile_content']));
      $board_id = $_POST['board_id'];
      
      $query = "INSERT INTO tiles (title, content, board_id) VALUES ('$tile_title','$tile_content','$board_id');";
      $result = pg_query($query) or die('Query failed: ' . pg_last_error());
      
      # Increment the board version
      update_board_version($board_id);

      header('location: index.php?board_id='.$board_id);
  }


  # Update tile position
  if($_POST['action'] == 'update_tile_position' && $_POST['tile_id'] != ''){
      
      $tile_id = $_POST['tile_id'];
      $left = $_POST['left'];
      $top = $_POST['top'];
      $board_id = $_POST['board_id'];

      $data = array(
        'tile_id' => $tile_id
      );

      # Update the tile position
      $query = "UPDATE tiles SET left_pos='$left', top_pos='$top' WHERE id = $tile_id";
      $result = pg_query($query) or die('Query failed: ' . pg_last_error());

      # Increment the board version
      update_board_version($board_id);

      # Get the latest board version
      $query = "SELECT version FROM  boards WHERE id = $board_id";
      $result = pg_query($query) or die('Query failed: ' . pg_last_error());      
      while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        $data['board_version'] = $line['version'];
      }

      # Increment the tile version
      $query = "UPDATE tiles SET version = version + 1 WHERE id = $tile_id";
      $result = pg_query($query) or die('Query failed: ' . pg_last_error());

      # Get the latest tile version
      $query = "SELECT version FROM  tiles WHERE id = $tile_id";
      $result = pg_query($query) or die('Query failed: ' . pg_last_error());      
      while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        $data['tile_version'] = $line['version'];
      }

      echo(json_encode($data));
      die();


  }


    # Update board
  if($_POST['action'] == 'edit_board' && $_POST['board_id'] != ''){
      
      $board_name = pg_escape_string($_POST['board_name']);
      $top_label = pg_escape_string($_POST['top_label']);
      $bottom_label = pg_escape_string($_POST['bottom_label']);
      $right_label = pg_escape_string($_POST['right_label']);
      $left_label = pg_escape_string($_POST['left_label']);
      $board_id = $_POST['board_id'];
      
      # Update the tile position
      $query = "UPDATE 
                  boards 
                SET 
                  board_name='$board_name', top_label='$top_label', bottom_label='$bottom_label', right_label='$right_label', left_label='$left_label' 
                WHERE id = $board_id";
      
      $result = pg_query($query) or die('Query failed: ' . pg_last_error());

      header('location: index.php?board_id='.$board_id);
      die();

  }


    # delete tile
  if($_POST['action'] == 'delete_tile' && $_POST['tile_id'] != ''){
      
      $tile_id = pg_escape_string($_POST['tile_id']);
      $board_id = $_POST['board_id'];

      error_log(print_r($_POST, true));
      
      # Update the tile position
      $query = "DELETE FROM tiles WHERE id = $tile_id";
      $result = pg_query($query) or die('Query failed: ' . pg_last_error());
      
      # Increment the board version
      update_board_version($board_id);

      echo($tile_id);
      die();

  }


  // CHeck if another user has updatd one of the tiles
  if($_POST['action'] == 'check_updates' && $_POST['board_id'] != ''){

    $board_id = $_POST['board_id'];
    $board_version = $_POST['board_version'];
    $board = array();
    $data = array();

    # See if the board has been updated
    $query = "SELECT * FROM boards WHERE id = $board_id and version > $board_version";
    $result = pg_query($query) or die('Query failed: ' . pg_last_error());      
    $rows = pg_num_rows($result);
    
    if($rows == 0)
    {
      $data['update'] = false;
      echo(json_encode($data));
      die();
    } 
    else
    {
      $data['update'] = true;
      # Load the board 
      while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        $data['board'] = $line;
      }

      # Load the tiles
      $query = "SELECT * FROM tiles WHERE board_id = '$board_id'";
      $result = pg_query($query) or die('Query failed: ' . pg_last_error());
      # Load the tiles as an array indexed by the id
      $data['tiles'] = array();
      while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        $data['tiles'][$line['id']] = array(
              'left' => $line['left_pos'],
              'top' => $line['top_pos'],
              'title' => $line['title'],
              'content' => $line['content'],
              'version' => $line['version']
            );
      }

      echo(json_encode($data));
      die();
    }

  }


}



?>


<!DOCTYPE html>
<html>
  <head>
    <title>Northstar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
 
    <!-- Bootstrap core CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.2/css/bootstrap.min.css" rel="stylesheet" media="screen">
 
    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="http://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.js"></script>
      <script src="http://cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.js"></script>
    <![endif]-->
    

    <style>
      
      .tile{
        width: 15%;
        border: 1px dotted silver;
        padding: 5px;
        position: absolute;
        float: left;
        z-index: 100;
        background: white
      }

      .metric{
        font-size: 1.5em;
        font-weight: bold;
      }

      .delete-tile{
        color: silver;
        float: right;
        position: relative;
        padding-left: 5px;
      }



    </style>


    </head>
  <body>



 <!-- Fixed navbar -->
    <nav class="navbar navbar-default navbar-fixed-top">
      <div class="container-fluid">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="#">Northstar</a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
          <ul class="nav navbar-nav">
            <li class="active"><a href="#">Home</a></li>
              <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Boards <span class="caret"></span></a>
              <ul class="dropdown-menu">
                <br />
                <li><a href="#" id="add-board">+ Add board</a></li>
                <li role="separator" class="divider"></li>
                <li class="dropdown-header">Boards</li>
                <?php foreach($boards as $key => $value) { ?>
                    <li><a href="index.php?board_id=<?php echo($key); ?>"><?php echo($value); ?></a></li>
                <?php } ?>
                
              </ul>
            </li>
            <li><a href="#" id="edit-board"><b><?php echo($board_name); ?> <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span> </b></a></li>
          </ul>


            <?php if(isset($board_id)){ ?>
                <ul class="nav navbar-nav navbar-right">
                  <?php if($board_id != 0) { ?>
                    <li><a href="index.php" id="add-tile">+ Add tile</a></li>
                  <?php } ?>
                </ul>
            <?php } ?>    
        </div><!--/.nav-collapse -->
      </div>
    </nav>

    <div class="container-fluid" style="padding-top: 50px;">


      <div id="board">

        <?php foreach ($tiles as $key => $value) { ?>

         <div class="draggable tile" data-version="<?php echo($value['version']); ?>" id ="tile-<?php echo($value['id']); ?>" 
              style="left:<?php echo($value['left_pos']); ?>;top:<?php echo($value['top_pos']); ?>">
            
            <a href="#" class="delete-tile" data-tid="<?php echo($value['id']); ?>" data-board="<?php echo($board_id); ?>">
              <span class="glyphicon glyphicon-remove" aria-hidden="true"></span>
            </a>
            <b><?php echo($value['title']); ?></b>
            <p><?php echo($value['content']); ?></p>
        
        </div>


        <?php } ?>

      </div>

      <div class="row" style="padding-top: 10px;">
          <div class="col-md-12" style="text-align: center;"><span class="metric"><?php echo($top_label); ?></span></sub></div>
      </div>

      

      <div class="row" style="padding-top: 20px;">
          <div class="col-md-6 top" style="border-right: 1px dotted silver; border-bottom:1px dotted silver"></div>
          <div class="col-md-6 top" style="border-bottom:1px dotted silver"></div>
      </div>


      <div class="row bottom-box">
          <div class="col-md-6 top" style="border-right: 1px dotted silver; padding-top: 20px;">
            <span style="padding-left: 20px;" class="metric"><?php echo($left_label); ?></span>
          </div>
          <div class="col-md-6" style="text-align: right; padding-top: 20px;">
            <span style="padding-right: 20px;" class="metric"><?php echo($right_label); ?></span>
          </div>
      </div>


      <div class="row">
          <div class="col-md-12" style="text-align: center;"><span style="padding-top: 20px;" class="metric"><?php echo($bottom_label); ?></span></div>
      </div>


      <div class="row">
          <div class="col-md-12"><sub id="board_version"><?php echo($board_version); ?></sub></div>
      </div>



    </div> <!-- /container -->


    <!-- Add board Modal -->
    <div id="addBoard" class="modal fade" role="dialog">
      <div class="modal-dialog">

        <!-- Modal content-->
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Add board</h4>
          </div>
          <div class="modal-body">
            <p>
                <form action="index.php" method="post">
                  <div class="form-group">
                    
                    <label for="board">Name:</label>
                    <input type="text" class="form-control" id="board" name="board_name">
                    
                    <label for="top_label">Top label:</label>
                    <input type="text" class="form-control" id="top_label" name="top_label">
                    
                    <label for="bottom_label">Bottom label:</label>
                    <input type="text" class="form-control" id="bottom_label" name="bottom_label">
                    
                    <label for="left_label">Left Label:</label>
                    <input type="text" class="form-control" id="left_label" name="left_label">
                    
                    <label for="right_label">Right Label:</label>
                    <input type="text" class="form-control" id="right_label" name="right_label">

                    <input type="hidden" name="action" value="add_board" />
                  </div>
                  <button type="submit" class="btn btn-default">Submit</button>
                </form>
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          </div>
        </div>

      </div>
    </div>


    <!-- Add board Modal -->
    <div id="editBoard" class="modal fade" role="dialog">
      <div class="modal-dialog">

        <!-- Modal content-->
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Edit <?php echo($board_name); ?></h4>
          </div>
          <div class="modal-body">
            <p>
                <form action="index.php" method="post">
                  <div class="form-group">
                    
                    <label for="board">Name:</label>
                    <input type="text" class="form-control" value="<?php echo($board_name); ?>" id="board" name="board_name">
                    
                    <label for="top_label">Top label:</label>
                    <input type="text" class="form-control" id="top_label"value="<?php echo($top_label); ?>" name="top_label">
                    
                    <label for="bottom_label">Bottom label:</label>
                    <input type="text" class="form-control" id="bottom_label" value="<?php echo($bottom_label); ?>" name="bottom_label">
                    
                    <label for="left_label">Left Label:</label>
                    <input type="text" class="form-control" id="left_label" value="<?php echo($left_label); ?>" name="left_label">
                    
                    <label for="right_label">Right Label:</label>
                    <input type="text" class="form-control" id="right_label" value="<?php echo($right_label); ?>" name="right_label">

                    <input type="hidden" name="board_id" value="<?php echo($board_id); ?>" />
                    <input type="hidden" name="action" value="edit_board" />
                  </div>
                  <button type="submit" class="btn btn-default">Submit</button>
                </form>
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          </div>
        </div>

      </div>
    </div>



    <!-- Add board Modal -->
    <div id="addTile" class="modal fade" role="dialog">
      <div class="modal-dialog">

        <!-- Modal content-->
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Add tile to <?php echo($board_name); ?></h4>
          </div>
          <div class="modal-body">
            <p>
                <form action="index.php" method="post">
                  <div class="form-group">
                    
                    <label for="tile_title">Title:</label>
                    <input type="text" class="form-control" id="tile_title" name="tile_title">

                    <label for="tile_cotent">Content:</label>
                    <textarea  class="form-control" id="tile_content" name="tile_content"></textarea>
                    <input type="hidden" name="board_id" value="<?php echo($board_id); ?>" />
                    <input type="hidden" name="action" value="add_tile" />
                  
                  </div>
                  <button type="submit" class="btn btn-default">Submit</button>
                </form>
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          </div>
        </div>

      </div>
    </div>



 
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.2/js/bootstrap.min.js"></script>
  

    <script>
        
        function drag_controller (event, ui, tile) {

              //Get the percentage location of the tiles 
              var top = (ui.position.top / $(window).height()) * 100; 
              var left = (ui.position.left / $(window).width()) * 100;

              tile.css("left",left + "%");
              tile.css("top",top + "%");

              $.post("index.php",
              {
                tile_id: tile.attr('id').replace("tile-", ""),
                left: left + "%",
                top: top + "%",
                action: 'update_tile_position',
                board_id: <?php echo($board_id); ?>
              },
              function(data, status){
                var versions = JSON.parse(data);
                $('#board_version').html(versions['board_version']);
                var tile_id = "tile-" + versions['tile_id'];
                $("#" + tile_id).attr('data-version', versions['tile_version']);
              
              });


            }



        $(".draggable").draggable({
            scroll: false,
            stop: function (event, ui) {
              var tile = $(this);
              drag_controller (event, ui, tile);
            }
        });

        var half_height = $(window).height() / 2.5;
        $('.top').height(half_height);


        $("#add-board").click(function(event){
            event.preventDefault();
            $('#addBoard').modal('show');
        });


        $("#add-tile").click(function(event){
            event.preventDefault();
            $('#addTile').modal('show');
        });


        $("#edit-board").click(function(event){
            event.preventDefault();
            $('#editBoard').modal('show');
        });


        $(".delete-tile").click(function(event){
            
            event.preventDefault();
            
            $.post("index.php",
            {
              tile_id: $(this).data('tid'),
              action: 'delete_tile',
              board_id: $(this).data('board')
            },
            function(data, status){
              var elem = document.querySelector('#tile-' + data);
              elem.parentNode.removeChild(elem);
            });


        });


        $(function(){
          setInterval(checkUpdates,4000);
        });

        function checkUpdates() {

          // stuff you want to do every second
          $.post("index.php",
          {
            board_version: $('#board_version').html(),
            action: 'check_updates',
            board_id: <?php echo($board_id); ?>
          },
          function(data, status){
             var updates = JSON.parse(data);
             
             if(updates['update'] == false)
             {
                //Do nothing
             } 
             else 
             {
              //Update the current board version
              $('#board_version').html(updates['board']['version']);

              //Loop through all the tiles and update their positions
              $.each(updates.tiles, function(index, element) {

                // Create the style
                var style = "left:" + element.left + ";top:" + element.top + ";" 

                // Tile exists so update it's position
                if($("#tile-" + index).length){  
                  $("#tile-" + index).attr('style', style);  
                }
                else
                {
                  
                  var content = "<b>" + element.title + "</b><p>" + element.content + "</p>";
                  var newDiv = '<div class="draggable tile ui-draggable ui-draggable-handle" id="tile-' + index + '" style="' + style + '">' + content + '</div>';  
                  $('#board').prepend(newDiv);
                  
                  $("#tile-" + index).draggable({
                      scroll: false,
                      stop: function (event, ui) {
                        var tile =  $("#tile-" + index);
                        drag_controller (event, ui, tile);
                      }
                  });

                }  
              });

              // CHeck there are no tiles that need deleting
              $( ".tile" ).each(function() {
                  
                  console.log($(this).attr('id'));
                  var tile_id = $(this).attr('id').replace("tile-", "");
                  if(!updates['tiles'].hasOwnProperty(tile_id)){
                    var elem = document.querySelector('#tile-' + tile_id);
                    elem.parentNode.removeChild(elem);                      
                  }
                  


              });              



             }
          });          

        }




    </script>	


  </body>
</html>

