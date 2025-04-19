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
                { data: 'actions', title: 'Hành động', orderable: false }
            ],
            language: {
                zeroRecords: 'Không có dữ liệu',
                emptyTable: 'Không có dữ liệu',
            }
        });

        // Fetch and populate company data
        function fetchCompanyData() {
            fetch('/api/index.php?model=company&method=listWithRepresentatives')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const companies = data.list.map(company => {
                            const representatives = company.representatives.map(rep => rep.name).join(', ');
                            return {
                                ...company,
                                representatives
                            };
                        });
                        dt_table.clear().rows.add(companies).draw();
                    } else {
                        alert('Không thể tải dữ liệu công ty!');
                    }
                });
        }

        fetchCompanyData();

        // Add event listeners for actions
        companyTable.addEventListener('click', function (e) {
            if (e.target.closest('.delete-button')) {
                const companyId = e.target.dataset.id;
                if (confirm('Bạn có chắc chắn muốn xóa công ty này?')) {
                    fetch(`/api/index.php?model=company&method=delete&id=${companyId}`, {
                        method: 'DELETE'
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Xóa thành công!');
                                fetchCompanyData();
                            } else {
                                alert('Xóa thất bại!');
                            }
                        });
                }
            }

            if (e.target.closest('.edit-button')) {
                const companyId = e.target.dataset.id;
                fetch(`/api/index.php?model=company&method=get&id=${companyId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const company = data.company;
                            document.querySelector('#modalEditID').value = company.id;
                            document.querySelector('#modalEditName').value = company.name;
                            document.querySelector('#modalEditType').value = company.type;
                            document.querySelector('#modalEditAddress').value = company.address;
                            document.querySelector('#modalEditPhone').value = company.phone;

                            const editModal = new bootstrap.Modal(document.getElementById('editCompanyModal'));
                            editModal.show();
                        } else {
                            alert('Không thể tải dữ liệu công ty!');
                        }
                    });
            }

            if (e.target.closest('.view-button')) {
                const companyId = e.target.dataset.id;
                fetch(`/api/index.php?model=company&method=get&id=${companyId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const company = data.company;
                            document.querySelector('#companyDetails').innerHTML = `
                                <p><strong>Tên công ty:</strong> ${company.name}</p>
                                <p><strong>Loại:</strong> ${company.type}</p>
                                <p><strong>Địa chỉ:</strong> ${company.address}</p>
                                <p><strong>Số điện thoại:</strong> ${company.phone}</p>
                            `;

                            fetch(`/api/index.php?model=company&method=listRepresentatives&company_id=${companyId}`)
                                .then(response => response.json())
                                .then(repData => {
                                    if (repData.success) {
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
                                    }
                                });

                            const viewModal = new bootstrap.Modal(document.getElementById('viewCompanyModal'));
                            viewModal.show();
                        } else {
                            alert('Không thể tải dữ liệu công ty!');
                        }
                    });
            }

            if (e.target.closest('.delete-rep-button')) {
                const repId = e.target.dataset.id;
                if (confirm('Bạn có chắc chắn muốn xóa người đại diện này?')) {
                    fetch(`/api/index.php?model=company&method=deleteRepresentative&id=${repId}`, {
                        method: 'DELETE'
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Xóa thành công!');
                                e.target.closest('tr').remove();
                            } else {
                                alert('Xóa thất bại!');
                            }
                        });
                }
            }

            if (e.target.closest('.edit-rep-button')) {
                const repId = e.target.dataset.id;
                fetch(`/api/index.php?model=company&method=getRepresentative&id=${repId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const rep = data.representative;
                            document.querySelector('#modalEditRepID').value = rep.id;
                            document.querySelector('#modalEditRepName').value = rep.name;
                            document.querySelector('#modalEditRepEmail').value = rep.email;
                            document.querySelector('#modalEditRepPhone').value = rep.phone;

                            const editRepModal = new bootstrap.Modal(document.getElementById('editRepresentativeModal'));
                            editRepModal.show();
                        } else {
                            alert('Không thể tải dữ liệu người đại diện!');
                        }
                    });
            }
        });

        // Add event listener for add company form
        document.querySelector('#formAddNewCompany').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('/api/index.php?model=company&method=add', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Thêm công ty thành công!');
                        fetchCompanyData();
                        const addModal = bootstrap.Modal.getInstance(document.getElementById('addCompanyModal'));
                        if (addModal) {
                            addModal.hide();
                        }
                    } else {
                        alert('Thêm công ty thất bại!');
                    }
                });
        });

        // Add event listener for edit company form
        document.querySelector('#formEditCompany').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('/api/index.php?model=company&method=edit', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cập nhật công ty thành công!');
                        fetchCompanyData();
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editCompanyModal'));
                        if (editModal) {
                            editModal.hide();
                        }
                    } else {
                        alert('Cập nhật công ty thất bại!');
                    }
                });
        });

        document.querySelector('#addRepresentativeButton').addEventListener('click', function () {
            const companyId = document.querySelector('#companyDetails').dataset.companyId;
            document.querySelector('#modalAddRepCompanyID').value = companyId;
            const addRepModal = new bootstrap.Modal(document.getElementById('addRepresentativeModal'));
            addRepModal.show();
        });

        document.querySelector('#formAddRepresentative').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('/api/index.php?model=company&method=addRepresentative', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Thêm người đại diện thành công!');
                        const addRepModal = bootstrap.Modal.getInstance(document.getElementById('addRepresentativeModal'));
                        if (addRepModal) {
                            addRepModal.hide();
                        }
                        fetchCompanyData();
                    } else {
                        alert('Thêm người đại diện thất bại!');
                    }
                });
        });

        document.querySelector('#formEditRepresentative').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('/api/index.php?model=company&method=editRepresentative', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cập nhật người đại diện thành công!');
                        const editRepModal = bootstrap.Modal.getInstance(document.getElementById('editRepresentativeModal'));
                        if (editRepModal) {
                            editRepModal.hide();
                        }
                        fetchCompanyData();
                    } else {
                        alert('Cập nhật người đại diện thất bại!');
                    }
                });
        });
    }
});