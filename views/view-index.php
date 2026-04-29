<?php

/** @var array $folders */
/** @var array $tasks */
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Site_Title ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>

<body>
  <div class="page">
    <div class="pageHeader">
      <div class="title">Dashboard</div>
      <div class="userPanel"><i class="fa fa-chevron-down"></i><span class="username">John Doe</span><img src="https://lh3.googleusercontent.com/_1UScAOZVnGjE6NUNjUehNfl5VIndppY1umPBUmVPxeSTp_X9xnKXkMFatMNQ6CqSyc" width="40" height="40"></div>
    </div>
    <div class="main">
      <div class="nav">
        <div class="searchbox">
          <div><i class="fa fa-search"></i>
            <input type="search" placeholder="Search" />
          </div>
        </div>
        <div class="menu">
          <div class="title">Folders</div>
          <ul class="folder-list">
            <li class="<?= isset($_GET['folder_id']) ? '' : 'active' ?>">
              <i class="fa fa-folder"></i>All
            </li>
            <?php foreach ($folders as $folder): ?>
              <li class="<?= ($_GET['folder_id'] == $folder->id) ? 'active' : '' ?>">
                <a href="?folder_id=<?= $folder->id ?>"><i class="fa fa-folder"></i><?= $folder->name ?></a>
                <a href="?delete_folder=<?= $folder->id ?>"><i class="fa fa-trash-o" onclick="return confirm('Are You Sure To Delete This Folder ?\n<?= $folder->name ?>')"></i></a>
              </li>
            <?php endforeach; ?>


          </ul>
        </div>
        <div>
          <input type="text" id="newFolderInput" style="width: 60%;margin-left: 5%;" placeholder="Add New Folder" />
          <button id="newFolderBtn" class="Btn clickable">+</button>
        </div>
      </div>
      <div class="view">
        <div class="viewHeader">
          <div class="title" style="width:50% ;">
            <input type="text" id="taskNameInput" style="width: 76%;margin-left: 5%;line-height: 17px;" placeholder="Add New Task">
            <button id="newTaskBtn" class="Btn clickable">+</button>
          </div>
          <div class="functions">
            <div class="button active">Add New Task</div>
            <div class="button">Completed</div>
            <div class="button inverz"><i class="fa fa-trash-o"></i></div>
          </div>
        </div>
        <div class="content">
          <div class="list">
            <div class="title">Today</div>
            <ul>
              <?php if (sizeof($tasks)): ?>
                <?php foreach ($tasks as $task): ?>
                  <li class="<?= $task->is_done ? 'checked' : ''; ?>">
                    <i class="fa <?= $task->is_done ? 'fa-check-square-o' : 'fa-square-o'; ?>"></i>
                    <span><?= $task->title ?></span>
                    <div class="info">
                      <span class="created-at">Created At <?= $task->created_at ?></span>
                      <a href="?delete_task=<?= $task->id ?>">
                        <i class="fa fa-trash-o" onclick="return confirm('Are You Sure To Delete This Task ?\n<?= $task->title ?>')"></i>
                      </a>
                    </div>
                  </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li>No Task Here...</li>
              <?php endif; ?>
            </ul>

          </div>

        </div>
      </div>
    </div>
  </div>
  <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script>
  <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function() {
      $('#newFolderBtn').click(function() {
        var input = $('input#newFolderInput');
        $.ajax({
          url: "bootstrap/ajaxHandler.php",
          method: "post",
          data: {
            action: "newFolder",
            foldername: input.val()
          },
          success: function(response) {
            if (response == '1') {
              location.reload();
            } else {
              alert(response);
            }
          },
          error: function(xhr, status, error) {
            alert("Error Creating Folder: " + error);
          }

        });
      });
    });
  </script>
</body>

</html>