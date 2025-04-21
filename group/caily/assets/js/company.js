'use strict';

// JavaScript for managing company data

document.addEventListener('DOMContentLoaded', function () {
    const companyTable = document.querySelector('.datatables-company');

    if (companyTable) {
        const dt_table = new DataTable(companyTable, {
            paging: true,
            searching: true,
            info: true,
            ordering: true,
            columns: [
                { data: 'id', title: 'ID' },
                { data: 'name', title: '会社名' },
                { data: 'type', title: 'タイプ' },
                { data: 'address', title: '住所' },
                { data: 'phone', title: '電話番号' },
                { data: 'created_at', title: '作成日' },
                { data: 'representatives', title: '担当者' },
                { data: '', title: '' },
            ],
            columnDefs: [
                {
                  // Actions
                  targets: -1,
                  title: '#',
                  searchable: false,
                  className: '',
                  orderable: false,
                  render: function (data, type, full, meta) {
                    return (
                      '<div class="d-inline-block">' +
                      '<a href="javascript:;" class="text-body edit-button" data-id="' + full.id + '"><i class="icon-base ti tabler-pencil"></i></a>' +
                      '<a href="javascript:;" class="text-danger delete-button" data-id="' + full.id + '"><i class="icon-base ti tabler-trash"></i></a>' +
                      '<a href="javascript:;" class="text-body view-button" data-id="' + full.id + '"><i class="icon-base ti tabler-eye"></i></a>' +
                      '</div>'
                    );
                  }
                }
              ],
            language: {
                zeroRecords: 'データがありません',
                emptyTable: 'データがありません',
            }
        });

        // Fetch and populate company data
        async function fetchCompanyData() {
            try {
                const response = await fetch('/api/index.php?model=customer&method=listWithRepresentatives');
                const data = await response.json();
                
                if (response.status !== 200 || !data || !data.list) {
                    handleErrors(data);
                    return;
                }

                const companies = Object.values(data.list).map(company => {
                    const representatives = company.representatives.map(rep => rep.name).join(', ');
                    return {
                        ...company,
                        representatives
                    };
                });
                dt_table.clear().rows.add(companies).draw();
            } catch (error) {
                console.error('会社データの取得エラー:', error);
                handleMessage(error.message);
            }
        }

        fetchCompanyData();

        // Add event listeners for actions
        companyTable.addEventListener('click', async function (e) {
            if (e.target.closest('.delete-button')) {
                const companyId = e.target.closest('.delete-button').dataset.id;
                if (confirm('この会社を削除してもよろしいですか？')) {
                    try {
                        const response = await fetch(`/api/index.php?model=customer&method=delete&id=${companyId}`, {
                            method: 'DELETE'
                        });
                        const data = await response.json();
                        
                        if (response.status !== 200 || !data || data.status !== 'success') {
                            handleErrors(data);
                            return;
                        }

                        handleMessage(data);
                        fetchCompanyData();
                    } catch (error) {
                        console.error('会社削除エラー:', error);
                        handleMessage(error.message);
                    }
                }
            }

            if (e.target.closest('.edit-button')) {
                const companyId = e.target.closest('.edit-button').dataset.id;
                try {
                    const response = await fetch(`/api/index.php?model=customer&method=get&id=${companyId}`);
                    const data = await response.json();
                    
                    if (response.status !== 200 || !data || !data.data) {
                        handleErrors(data);
                        return;
                    }

                    const company = data.data;
                    document.querySelector('#modalEditID').value = company.id;
                    document.querySelector('#modalEditName').value = company.name;
                    document.querySelector('#modalEditType').value = company.type;
                    document.querySelector('#modalEditAddress').value = company.address;
                    document.querySelector('#modalEditPhone').value = company.phone;

                    const editModal = new bootstrap.Modal(document.getElementById('editCompanyModal'));
                    editModal.show();
                } catch (error) {
                    console.error('会社データの取得エラー:', error);
                    handleMessage(error.message);
                }
            }

            if (e.target.closest('.view-button')) {
                const companyId = e.target.closest('.view-button').dataset.id;
                try {
                    const response = await fetch(`/api/index.php?model=customer&method=get&id=${companyId}`);
                    const data = await response.json();
                    
                    if (response.status !== 200 || !data || !data.data) {
                        handleMessage(data);
                        return;
                    }

                    const company = data.data;
                    document.querySelector('#companyDetails').innerHTML = `
                        <p><strong>会社名:</strong> ${company.name}</p>
                        <p><strong>タイプ:</strong> ${company.type}</p>
                        <p><strong>住所:</strong> ${company.address}</p>
                        <p><strong>電話番号:</strong> ${company.phone}</p>
                    `;

                    document.querySelector('#companyDetails').dataset.companyId = companyId;

                    const repResponse = await fetch(`/api/index.php?model=customer&method=listRepresentatives&company_id=${companyId}`);
                    const repData = await repResponse.json();
                    
                    if (repResponse.status !== 200 || !repData || !repData.list) {
                        handleMessage(repData);
                        return;
                    }

                    const repTable = document.querySelector('.datatables-representatives tbody');
                    repTable.innerHTML = '';
                    repData.list.forEach(rep => {
                        repTable.innerHTML += `
                            <tr>
                                <td>${rep.id}</td>
                                <td>${rep.name}</td>
                                <td>${rep.email}</td>
                                <td>${rep.phone}</td>
                                <td>
                                    <button class="btn btn-danger btn-sm delete-rep-button" data-id="${rep.id}">削除</button>
                                    <button class="btn btn-primary btn-sm edit-rep-button" data-id="${rep.id}">編集</button>
                                </td>
                            </tr>
                        `;
                    });

                    const viewModal = new bootstrap.Modal(document.getElementById('viewCompanyModal'));
                    viewModal.show();
                } catch (error) {
                    console.error('会社詳細の取得エラー:', error);
                    handleMessage(error.message);
                }
            }
            
        });

        document.querySelector('.datatables-representatives').addEventListener('click', async function (e) {
            if (e.target.closest('.delete-rep-button')) {
                const repId = e.target.closest('.delete-rep-button').dataset.id;
                if (confirm('この担当者を削除してもよろしいですか？')) {
                    try {
                        const response = await fetch(`/api/index.php?model=customer&method=deleteRepresentative&id=${repId}`, {
                            method: 'DELETE'
                        });
                        const data = await response.json();
                        
                        if (response.status !== 200 || !data || data.status !== 'success') {
                            handleMessage(data);
                            return;
                        }

                        handleMessage(data);
                        e.target.closest('tr').remove();
                    } catch (error) {
                        console.error('担当者削除エラー:', error);
                        handleMessage(error.message);
                    }
                }
            }

            if (e.target.closest('.edit-rep-button')) {
                //close viewCompanyModal
                const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewCompanyModal'));
                if (viewModal) {
                    viewModal.hide();
                }
                const repId = e.target.closest('.edit-rep-button').dataset.id;
                try {
                    const response = await fetch(`/api/index.php?model=customer&method=getRepresentative&id=${repId}`);
                    const data = await response.json();
                    
                    if (response.status !== 200 || !data || !data.data) {
                        handleMessage(data);
                        return;
                    }

                    const rep = data.data;
                    document.querySelector('#modalEditRepID').value = rep.id;
                    document.querySelector('#modalEditRepName').value = rep.name;
                    document.querySelector('#modalEditRepEmail').value = rep.email;
                    document.querySelector('#modalEditRepPhone').value = rep.phone;

                    const editRepModal = new bootstrap.Modal(document.getElementById('editRepresentativeModal'));
                    editRepModal.show();
                } catch (error) {
                    console.error('担当者データの取得エラー:', error);
                    handleMessage(error.message);
                }
            }
        });

        // Add event listener for add company form
        document.querySelector('#formAddNewCompany').addEventListener('submit', async function (e) {
            e.preventDefault();
            try {
                const formData = new FormData(this);
                const response = await fetch('/api/index.php?model=customer&method=add', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (response.status !== 200 || !data || data.status !== 'success') {
                    handleMessage(data);
                    return;
                }

                handleMessage(data);
                fetchCompanyData();
                const addModal = bootstrap.Modal.getInstance(document.getElementById('addCompanyModal'));
                if (addModal) {
                    addModal.hide();
                }
            } catch (error) {
                console.error('会社追加エラー:', error);
                handleMessage(error.message);
            }
        });

        // Add event listener for edit company form
        document.querySelector('#formEditCompany').addEventListener('submit', async function (e) {
            e.preventDefault();
            try {
                const formData = new FormData(this);
                const response = await fetch('/api/index.php?model=customer&method=edit&id=' + document.querySelector('#modalEditID').value, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (response.status !== 200 || !data || data.status !== 'success') {
                    handleMessage(data);
                    return;
                }

                handleMessage(data);
                fetchCompanyData();
                const editModal = bootstrap.Modal.getInstance(document.getElementById('editCompanyModal'));
                if (editModal) {
                    editModal.hide();
                }
            } catch (error) {
                console.error('会社更新エラー:', error);
                handleMessage(error.message);
            }
        });

        document.querySelector('#addRepresentativeButton').addEventListener('click', function () {
            const companyId = document.querySelector('#companyDetails').dataset.companyId;
            document.querySelector('#modalAddRepCompanyID').value = companyId;
            const addRepModal = new bootstrap.Modal(document.getElementById('addRepresentativeModal'));
            addRepModal.show();
             //close viewCompanyModal
             const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewCompanyModal'));
             if (viewModal) {
                 viewModal.hide();
             }
        });

        document.querySelector('#formAddRepresentative').addEventListener('submit', async function (e) {
            e.preventDefault();
            try {
               
                const formData = new FormData(this);
                const response = await fetch('/api/index.php?model=customer&method=addRepresentative', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (response.status !== 200 || !data || data.status !== 'success') {
                    handleMessage(data);
                    return;
                }

                handleMessage(data);
                const addRepModal = bootstrap.Modal.getInstance(document.getElementById('addRepresentativeModal'));
                if (addRepModal) {
                    addRepModal.hide();
                }
                fetchCompanyData();
            } catch (error) {
                console.error('担当者追加エラー:', error);
                handleMessage(error.message);
            }
        });

        document.querySelector('#formEditRepresentative').addEventListener('submit', async function (e) {
            e.preventDefault();
            try {
                const formData = new FormData(this);
                const response = await fetch('/api/index.php?model=customer&method=editRepresentative&id=' + document.querySelector('#modalEditRepID').value, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (response.status !== 200 || !data || data.status !== 'success') {
                    handleErrors(data);
                    return;
                }

                handleMessage(data);
                const editRepModal = bootstrap.Modal.getInstance(document.getElementById('editRepresentativeModal'));
                if (editRepModal) {
                    editRepModal.hide();
                }
                fetchCompanyData();
            } catch (error) {
                console.error('担当者更新エラー:', error);
                handleMessage(error.message);
            }
        });
    }
});

// Error handling function
function handleMessage(data) {
    if (!data) {
        showMessage('不明なエラーが発生しました！', true);
        return;
    }
    console.log(data);
   
    if(data.message_code){
        var message_code = parseInt(data.message_code);
        var status = data.status == 'success' ? false : true;
        switch (message_code) {
            case 0:
                showMessage('操作に失敗しました！', status);
                break;
            case 1:
                showMessage('会社の更新に成功しました！', status);
                break;
            case 2:
                showMessage('会社の更新に成功しました！', status);
                break;
            case 3:
                showMessage('会社の削除に成功しました！', status);
                break;
            case 4:
                showMessage('担当者の追加に成功しました！', status);
                break;
            case 5:
                showMessage('担当者の削除に成功しました！', status);
                break;
            case 6:
                showMessage('担当者の更新に成功しました！', status);
                break;
            case 7:
                showMessage('会社は既に存在しています！', status);
                break;
            case 8:
                showMessage('担当者は既に存在しています！', status);
                break;
            default:
                showMessage('不明なエラーが発生しました！', status);
        }
    } else{
        showMessage(data, true);
    }
}