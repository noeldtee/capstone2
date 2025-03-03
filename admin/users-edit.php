<?php include ('includes/header.php') ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>
                        Edit User
                        <a href="users.php" class="btn btn-primary float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">

                    <form action="users-code.php" method="POST">

                        <?php
                            $paramResult = checkParamId('id');
                            if(!is_numeric($paramResult)){
                                echo '<h5>'.$paramResult.'</h5>';
                                return false;
                            }

                            $user = getById('users', checkParamId('id'));
                            if($user['status'] == 200)
                            {
                                ?>

                            <input type="hidden" name="userID" value="<?= $user['data']['id'] ;?>" required>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label>First Name</label>
                                                <input type="text" name="firstname" value="<?= $user['data']['firstname'] ;?>" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label>Last Name</label>
                                                <input type="text" name="lastname" value="<?= $user['data']['lastname'] ;?>" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label>Email</label>
                                                <input type="text" name="email" value="<?= $user['data']['email'] ;?>" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label>Password</label>
                                                <input type="text" name="password" value="<?= $user['data']['password'] ;?>" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label>Select Role</label>
                                                <select name="role" class="form-select" required>
                                                    <option value="">Select Role</option>
                                                    <option value="admin" <?= $user['data']['role'] == 'admin' ? 'selected':'' ;?>>Admin</option>
                                                    <option value="staff" <?= $user['data']['role'] == 'staff' ? 'selected':'' ;?>>Staff</option>
                                                    <option value="cashier" <?= $user['data']['role'] == 'cashier' ? 'selected':'' ;?>>Cashier</option>
                                                    <option value="student" <?= $user['data']['role'] == 'student' ? 'selected':'' ;?>>Student</option>
                                                    <option value="alumni" <?= $user['data']['role'] == 'alumni' ? 'selected':'' ;?>>Alumni</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label>Is Ban</label>
                                                <br/>
                                                <input type="checkbox" name="is_ban" <?= $user['data']['is_ban'] == true ? 'checked':'' ;?> style="width:30 px;height:30 px" />
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 text-end">
                                                <br/>
                                                <button type="submit" name="updateUser" class="btn btn-primary">Update</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php
                            }
                            else
                            {
                                echo '<h5>'.$user['message'].'</h5>';
                            }
                        ?>



                    </form>
                </div>
            </div>
        </div>
    </div>

<?php include ('includes/footer.php') ?>