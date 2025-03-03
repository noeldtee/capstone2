<?php include ('includes/header.php') ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>
                        User List
                        <a href="users-create.php" class="btn btn-primary float-end">Add Users</a>
                    </h4>
                </div>
                <div class="card-body">

                <?= alertMessage(); ?>

                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Id</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Is Ban</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                
                                $users = getAll('users');
                                if(mysqli_num_rows($users) > 0)
                                {
                                    foreach($users as $userItem)
                                    {
                                        ?>
                                        <tr>
                                            <td><?= $userItem['id'] ?></td>
                                            <td><?= $userItem['firstname'] ?></td>
                                            <td><?= $userItem['lastname'] ?></td>
                                            <td><?= $userItem['email'] ?></td>
                                            <td><?= $userItem['role'] ?></td>
                                            <td><?= $userItem['is_ban'] == 1 ?'Inactive': 'Active'; ?></td>
                                            <td>
                                                <a href="users-edit.php?id=<?= $userItem['id'] ?>" class="btn btn-success btn-sm">Edit</a>
                                                <a href="users-delete.php?id=<?= $userItem['id'] ?>" 
                                                    class="btn btn-danger btn-sm mx-2"
                                                    onclick="return confirm('Are you sure you want to delete this record?')"
                                                    >
                                                    Delete</a>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                                else
                                {
                                    ?>
                                    <tr>
                                        <td colspan="7">No Record Found</td>
                                    </tr>
                                    <?php
                                }

                            ?>
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>

<?php include ('includes/footer.php') ?>