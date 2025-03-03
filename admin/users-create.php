<?php include ('includes/header.php') ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>
                        Add User
                        <a href="users.php" class="btn btn-primary float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">

                    <?= alertMessage(); ?>

                    <form action="users-code.php" method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label>First Name</label>
                                    <input type="text" name="firstname" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label>Last Name</label>
                                    <input type="text" name="lastname" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label>Email</label>
                                    <input type="text" name="email" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label>Password</label>
                                    <input type="text" name="password" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label>Select Role</label>
                                    <select name="role" class="form-select">
                                        <option value="">Select Role</option>
                                        <option value="admin">Admin</option>
                                        <option value="staff">Staff</option>
                                        <option value="cashier">Cashier</option>
                                        <option value="student">Student</option>
                                        <option value="alumni">Alumni</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label>Is Ban</label>
                                    <br/>
                                    <input type="checkbox" name="is_ban" style="width:30 px;height:30 px" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 text-end">
                                    <br/>
                                    <button type="submit" name="saveUser" class="btn btn-primary">Submit</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php include ('includes/footer.php') ?>