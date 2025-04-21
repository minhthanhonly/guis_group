<?php

require_once('../application/loader.php');
$view->heading('顧客会社管理');

?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card mb-6">
        <div class="card-header d-flex justify-content-between">
            <h5 class="card-title mb-0">顧客会社一覧</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">会社を追加</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="datatables-company table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>会社名</th>
                            <th>タイプ</th>
                            <th>住所</th>
                            <th>電話番号</th>
                            <th>作成日</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 会社データはここに動的に表示されます -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Company Modal -->
<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-company">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">会社を追加</h4>
                </div>
                <form id="formAddNewCompany" class="row g-3">
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddName">会社名</label>
                        <input type="text" id="modalAddName" name="name" class="form-control" placeholder="会社名を入力" autofocus required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddType">タイプ</label>
                        <select id="modalAddType" name="type" class="form-control" required>
                            <option value="client">顧客</option>
                            <option value="partner">パートナー</option>
                        </select>
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddAddress">住所</label>
                        <input type="text" id="modalAddAddress" name="address" class="form-control" placeholder="住所を入力" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddPhone">電話番号</label>
                        <input type="text" id="modalAddPhone" name="phone" class="form-control" placeholder="電話番号を入力" required />
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitAddCompany" type="submit" class="btn btn-primary me-sm-4 me-1">送信</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">キャンセル</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-edit-company">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">会社を編集</h4>
                </div>
                <form id="formEditCompany" class="row g-3">
                    <input type="hidden" name="id" id="modalEditID" />
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditName">会社名</label>
                        <input type="text" id="modalEditName" name="name" class="form-control" placeholder="会社名を入力" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditType">タイプ</label>
                        <select id="modalEditType" name="type" class="form-control" required>
                            <option value="client">顧客</option>
                            <option value="partner">パートナー</option>
                        </select>
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditAddress">住所</label>
                        <input type="text" id="modalEditAddress" name="address" class="form-control" placeholder="住所を入力" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditPhone">電話番号</label>
                        <input type="text" id="modalEditPhone" name="phone" class="form-control" placeholder="電話番号を入力" required />
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitEditCompany" type="submit" class="btn btn-primary me-sm-4 me-1">送信</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">キャンセル</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Company Modal -->
<div class="modal fade" id="viewCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-view-company">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">会社情報</h4>
                </div>
                <div id="companyDetails">
                    <!-- 会社の詳細はここに動的に読み込まれます -->
                </div>
                <hr>
                <div class="text-center mb-6">
                    <h5 class="role-title">担当者一覧</h5>
                </div>
                <div class="table-responsive">
                    <table class="datatables-representatives table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>名前</th>
                                <th>メール</th>
                                <th>電話番号</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- 担当者データはここに動的に表示されます -->
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-primary" id="addRepresentativeButton">担当者を追加</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Representative Modal -->
<div class="modal fade" id="addRepresentativeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-representative">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">担当者を追加</h4>
                </div>
                <form id="formAddRepresentative" class="row g-3">
                    <input type="hidden" name="company_id" id="modalAddRepCompanyID" />
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddRepName">名前</label>
                        <input type="text" id="modalAddRepName" name="name" class="form-control" placeholder="担当者名を入力" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddRepEmail">メール</label>
                        <input type="email" id="modalAddRepEmail" name="email" class="form-control" placeholder="担当者のメールを入力" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddRepPhone">電話番号</label>
                        <input type="text" id="modalAddRepPhone" name="phone" class="form-control" placeholder="担当者の電話番号を入力" required />
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitAddRepresentative" type="submit" class="btn btn-primary me-sm-4 me-1">送信</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">キャンセル</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Representative Modal -->
<div class="modal fade" id="editRepresentativeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-edit-representative">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">担当者情報を編集</h4>
                </div>
                <form id="formEditRepresentative" class="row g-3">
                    <input type="hidden" name="id" id="modalEditRepID" />
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditRepName">名前</label>
                        <input type="text" id="modalEditRepName" name="name" class="form-control" placeholder="担当者名を入力" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditRepEmail">メール</label>
                        <input type="email" id="modalEditRepEmail" name="email" class="form-control" placeholder="担当者のメールを入力" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditRepPhone">電話番号</label>
                        <input type="text" id="modalEditRepPhone" name="phone" class="form-control" placeholder="担当者の電話番号を入力" required />
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitEditRepresentative" type="submit" class="btn btn-primary me-sm-4 me-1">送信</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">キャンセル</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>
<script src="<?=ROOT?>assets/js/company.js"></script>