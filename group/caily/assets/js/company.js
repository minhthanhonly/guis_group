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
                { data: 'name', title: 'Tên công ty' },
                { data: 'type', title: 'Loại' },
                { data: 'address', title: 'Địa chỉ' },
                { data: 'phone', title: 'Số điện thoại' },
                { data: 'created_at', title: 'Ngày tạo' },
                { data: 'representatives', title: 'Người đại diện' },
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
                zeroRecords: 'Không có dữ liệu',
                emptyTable: 'Không có dữ liệu',
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
                console.error('Error fetching company data:', error);
                showMessage(error.message, true);
            }
        }

        fetchCompanyData();

        // Add event listeners for actions
        companyTable.addEventListener('click', async function (e) {
            if (e.target.closest('.delete-button')) {
                const companyId = e.target.closest('.delete-button').dataset.id;
                if (confirm('Bạn có chắc chắn muốn xóa công ty này?')) {
                    try {
                        const response = await fetch(`/api/index.php?model=customer&method=delete&id=${companyId}`, {
                            method: 'DELETE'
                        });
                        const data = await response.json();
                        
                        if (response.status !== 200 || !data || data.status !== 'success') {
                            handleErrors(data);
                            return;
                        }

                        showMessage(data.message_code);
                        fetchCompanyData();
                    } catch (error) {
                        console.error('Error deleting company:', error);
                        showMessage(error.message, true);
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
                    console.error('Error fetching company data:', error);
                    showMessage(error.message, true);
                }
            }

            if (e.target.closest('.view-button')) {
                const companyId = e.target.closest('.view-button').dataset.id;
                try {
                    const response = await fetch(`/api/index.php?model=customer&method=get&id=${companyId}`);
                    const data = await response.json();
                    
                    if (response.status !== 200 || !data || !data.data) {
                        handleErrors(data);
                        return;
                    }

                    const company = data.data;
                    document.querySelector('#companyDetails').innerHTML = `
                        <p><strong>Tên công ty:</strong> ${company.name}</p>
                        <p><strong>Loại:</strong> ${company.type}</p>
                        <p><strong>Địa chỉ:</strong> ${company.address}</p>
                        <p><strong>Số điện thoại:</strong> ${company.phone}</p>
                    `;
                    
                    document.querySelector('#companyDetails').dataset.companyId = companyId;

                    const repResponse = await fetch(`/api/index.php?model=customer&method=listRepresentatives&company_id=${companyId}`);
                    const repData = await repResponse.json();
                    
                    if (repResponse.status !== 200 || !repData || !repData.list) {
                        handleErrors(repData);
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
                                    <button class="btn btn-danger btn-sm delete-rep-button" data-id="${rep.id}">Xóa</button>
                                    <button class="btn btn-primary btn-sm edit-rep-button" data-id="${rep.id}">Sửa</button>
                                </td>
                            </tr>
                        `;
                    });

                    const viewModal = new bootstrap.Modal(document.getElementById('viewCompanyModal'));
                    viewModal.show();
                } catch (error) {
                    console.error('Error fetching company details:', error);
                    showMessage(error.message, true);
                }
            }

            if (e.target.closest('.delete-rep-button')) {
                const repId = e.target.closest('.delete-rep-button').dataset.id;
                if (confirm('Bạn có chắc chắn muốn xóa người đại diện này?')) {
                    try {
                        const response = await fetch(`/api/index.php?model=customer&method=deleteRepresentative&id=${repId}`, {
                            method: 'DELETE'
                        });
                        const data = await response.json();
                        
                        if (response.status !== 200 || !data || data.status !== 'success') {
                            handleErrors(data);
                            return;
                        }

                        showMessage(data.message_code);
                        e.target.closest('tr').remove();
                    } catch (error) {
                        console.error('Error deleting representative:', error);
                        showMessage(error.message, true);
                    }
                }
            }

            if (e.target.closest('.edit-rep-button')) {
                const repId = e.target.closest('.edit-rep-button').dataset.id;
                try {
                    const response = await fetch(`/api/index.php?model=customer&method=getRepresentative&id=${repId}`);
                    const data = await response.json();
                    
                    if (response.status !== 200 || !data || !data.data) {
                        handleErrors(data);
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
                    console.error('Error fetching representative data:', error);
                    showMessage(error.message, true); 
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
                    handleErrors(data);
                    return;
                }

                showMessage(data.message_code);
                fetchCompanyData();
                const addModal = bootstrap.Modal.getInstance(document.getElementById('addCompanyModal'));
                if (addModal) {
                    addModal.hide();
                }
            } catch (error) {
                console.error('Error adding company:', error);
                showMessage(error.message, true);
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
                    handleErrors(data);
                    return;
                }

                showMessage(data.message);
                fetchCompanyData();
                const editModal = bootstrap.Modal.getInstance(document.getElementById('editCompanyModal'));
                if (editModal) {
                    editModal.hide();
                }
            } catch (error) {
                console.error('Error updating company:', error);
                showMessage(error.message, true);
            }
        });

        document.querySelector('#addRepresentativeButton').addEventListener('click', function () {
            const companyId = document.querySelector('#companyDetails').dataset.companyId;
            document.querySelector('#modalAddRepCompanyID').value = companyId;
            const addRepModal = new bootstrap.Modal(document.getElementById('addRepresentativeModal'));
            addRepModal.show();
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
                    handleErrors(data);
                    return;
                }

                showMessage(data.message_code);
                const addRepModal = bootstrap.Modal.getInstance(document.getElementById('addRepresentativeModal'));
                if (addRepModal) {
                    addRepModal.hide();
                }
                fetchCompanyData();
            } catch (error) {
                console.error('Error adding representative:', error);
                showMessage(error.message, true);
            }
        });

        document.querySelector('#formEditRepresentative').addEventListener('submit', async function (e) {
            e.preventDefault();
            try {
                const formData = new FormData(this);
                const response = await fetch('/api/index.php?model=customer&method=editRepresentative', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (response.status !== 200 || !data || data.status !== 'success') {
                    handleErrors(data);
                    return;
                }

                showMessage(data.message_code);
                const editRepModal = bootstrap.Modal.getInstance(document.getElementById('editRepresentativeModal'));
                if (editRepModal) {
                    editRepModal.hide();
                }
                fetchCompanyData();
            } catch (error) {
                console.error('Error updating representative:', error);
                showMessage(error.message, true);
            }
        });
    }
});

// Error handling function
function handleErrors(data) {
    if (!data) {
        showMessage('Lỗi không xác định!', true);
        return;
    }
    
    switch (data.message_code) {
        case 0:
            showMessage('Thao tác thất bại!', true);
            break;
        case 1:
            showMessage('Thêm công ty thất bại!', true);
            break;
        case 2:
            showMessage('Cập nhật công ty thất bại!', true);
            break;
        case 3:
            showMessage('Xóa công ty thất bại!', true);
            break;
        case 4:
            showMessage('Thêm người đại diện thất bại!', true);
            break;
        case 5:
            showMessage('Xóa người đại diện thất bại!', true);
            break;
        case 6:
            showMessage('Cập nhật người đại diện thất bại!', true);
            break;
        default:
            showMessage('Lỗi không xác định!', true);
    }
}