'use strict';

var isSameUser = false;
var dt_table = null
var timecard_start_date = $('html').attr('data-timecard-start');
var USER_LIST = [];
var GROUP_LIST = [];
var sum = {
  timecard_time: 0,
  timecard_timeover: 0,
  timecard_timeholiday: 0,
  work_days: 0,
  total_days: 0
}
var user_role = 'member';
if (typeof USER_ROLE !== 'undefined') {
  user_role = USER_ROLE;
}
var holidayList = [];



function updateAnalytics(data) {
  const timecard_time = document.getElementById('work_time');
  const timecard_timeover = document.getElementById('over_time');
  const timecard_timeholiday = document.getElementById('holiday_time');
  const work_days = document.getElementById('work_days');

  timecard_time.innerHTML = formatTime(data.timecard_time);
  timecard_timeover.innerHTML = formatTime(data.timecard_timeover);
  timecard_timeholiday.innerHTML = formatTime(data.timecard_timeholiday);
  work_days.innerHTML = `${data.work_days} / ${data.total_days}`;
}

function findUsername(userid) {
  const user = USER_LIST.find(user => user.userid === userid);
  return user ? user.realname : userid;
}

async function get_timecard(user, year, month) {
  holidayList = [];
  isSameUser = false;
  sum = {
    timecard_time: 0,
    timecard_timeover: 0,
    timecard_timeholiday: 0,
    work_days: 0,
    total_days: 0
  }
  const response = await axios.get(`/api/index.php?model=timecard&method=timecardlist&member=${user}&year=${year}&month=${month}`);
  const timcard_type = document.getElementById('timecard_type');
  const timecard_title = document.getElementById('timecard_title');
  if (timcard_type && response.data.config) {
    timcard_type.innerHTML = response.data.config.config_name;
  }
  if (timecard_title && response.data.owner) {
    timecard_title.innerHTML = response.data.owner.realname;
  }
  // check if the response is successful
  if (response.status !== 200 || !response.data || !response.data.list) {
    handleErrors(response.data);
  }
  holidayList = response.data.holidays;
  // loop through the day in that month, check if not exist then add it
  const list = response.data.list;
  isSameUser = response.data.isSameUser;
  let days = {};
  // Parse the start date using timecard_start_date, year, and month

  let start = new Date(`${year}-${month}-${timecard_start_date}`);
  if (timecard_start_date != '1') {
    start.setMonth(start.getMonth() - 1); // Set the start date to the first day of the month
  }
  let end = new Date(start);
  end.setMonth(end.getMonth() + 1); // Set the end date to one month after the start date
  end.setDate(end.getDate() - 1); // Adjust to the last day of the month
  const countDate = Math.floor((end - start) / (1000 * 60 * 60 * 24));
  sum.total_days = countDate + 1;
  let countWorkDays = 0;
  for (let i = 0; i <= countDate; i++) {
    let thisDay = start.addDays(i);
    let day = thisDay.getDate();
    let month = thisDay.getMonth() + 1;
    let foundItem = list.find(item => {
      return parseInt(item.timecard_day) === day
    });
    if (foundItem) {
      if (foundItem.holiday == 1) {
        foundItem.timecard_timeinterval = '';
        foundItem.timecard_timeover = '';
        foundItem.timecard_timeholiday = foundItem.timecard_time;
        foundItem.timecard_time = '';
        var timeHoliday = foundItem.timecard_timeholiday.split(':');
        sum.timecard_timeholiday += parseInt(timeHoliday[0]) * 60 + parseInt(timeHoliday[1]);
      } else {
        foundItem.timecard_timeholiday = '';
      }
      days[i] = foundItem;

      if (foundItem.timecard_open) {
        countWorkDays++;
      }

      if (foundItem.timecard_time) {
        var time = foundItem.timecard_time.split(':');
        sum.timecard_time += parseInt(time[0]) * 60 + parseInt(time[1]);
      }
      if (foundItem.timecard_timeover) {
        var timeover = foundItem.timecard_timeover.split(':');
        sum.timecard_timeover += parseInt(timeover[0]) * 60 + parseInt(timeover[1]);
      }

    } else {
      days[i] = {
        id: null,
        timecard_year: year,
        timecard_month: month,
        timecard_day: day,
        timecard_date: `${year}-${month}-${day}`,
        timecard_open: '',
        timecard_close: '',
        timecard_interval: '',
        timecard_originalopen: '',
        timecard_originalclose: '',
        timecard_originalinterval: '',
        timecard_time: '',
        timecard_timeover: '',
        timecard_timeinterval: '',
        timecard_comment: '',
        timecard_timeholiday: '',
        owner: user,
        editor: '',
        created: '',
        updated: ''
      };
    }
    sum.work_days = countWorkDays;
  }
  updateAnalytics(sum);
  return days;
}

async function get_users() {
  const response = await axios.get(`/api/index.php?model=user&method=getList`);
  // check if the response is successful
  if (response.status !== 200 || !response.data || !response.data.list) {
    handleErrors(response.data);
  }

  return response.data.list;
}

async function get_groups() {
  const response = await axios.get(`/api/index.php?model=group&method=getList`);
  // check if the response is successful
  if (response.status !== 200 || !response.data || !response.data.list) {
    handleErrors(response.data);
  }
  response.data.list;
  return response.data.list;
}

async function fetchUser(group_id) {
  const slUser = document.getElementById('selectpickerUser');

  if (!slUser) {
    return;
  }

  // Destroy existing selectpicker before clearing innerHTML
  $(slUser).selectpicker('destroy');

  slUser.innerHTML = '';
  if (USER_LIST.length == 0) {
    USER_LIST = await get_users();
  }
  const currentUser = slUser.getAttribute('data-current-user');
  USER_LIST.forEach(user => {
    if (group_id == user.user_group) {
      if (currentUser === user.userid) {
        slUser.innerHTML += `<option data-icon="icon-base ti tabler-user" value="${user.userid}" selected>${user.realname}</option>`;
      } else {
        slUser.innerHTML += `<option data-icon="icon-base ti tabler-user" value="${user.userid}">${user.realname}</option>`;
      }
    }
  });

  // Reinitialize selectpicker after updating options
  $(slUser).selectpicker();
}

async function fetchGroup() {
  const slGroup = document.getElementById('selectpickerGroup');
  if (!slGroup) {
    return;
  }
  slGroup.innerHTML = '';
  if (GROUP_LIST.length == 0) {
    GROUP_LIST = await get_groups();
  }
  const currentGroup = slGroup.getAttribute('data-current-group');

  GROUP_LIST.forEach(group => {
    if (currentGroup === group.id) {
      slGroup.innerHTML += `<option data-icon="icon-base ti tabler-users-group" value="${group.id}" selected>${group.group_name}</option>`;
    } else {
      slGroup.innerHTML += `<option data-icon="icon-base ti tabler-users-group" value="${group.id}">${group.group_name}</option>`;
    }
  });

  $(slGroup).selectpicker('refresh');

  fetchUser(currentGroup);

}

function decodeHtmlEntities(str) {
  return str.replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'");
}

//add event listener for selectpicker
function addEvent() {
  const slUser = document.getElementById('selectpickerUser');
  const slGroup = document.getElementById('selectpickerGroup');
  const monthInput = document.getElementById('timecard-month-input');
  const recalc = document.querySelector('[data-recalculation]');

  let editTimecardOpen = flatpickr("#editTimecardOpen", {
    dateFormat: "H:i",
    locale: "ja",
    time_24hr: true,
    enableTime: true,
    noCalendar: true,
    inline: true,
    minuteIncrement: 1,
  });
  let editTimecardClose = flatpickr("#editTimecardClose", {
    dateFormat: "H:i",
    locale: "ja",
    time_24hr: true,
    enableTime: true,
    noCalendar: true,
    inline: true,
    minuteIncrement: 1,
  });
  // Add event listener for the selectpicker
  if (slUser) {
    slUser.addEventListener('change', async function () {
      changeData()
    });
  }
  if (slGroup) {
    slGroup.addEventListener('change', async function () {
      fetchUser(slGroup.value);
      changeData()
    });
  }
  // Add event listener for the month input
  if (monthInput) {
    monthInput.addEventListener('change', async function () {
      changeData()
    });
  }
  const viewModal = new bootstrap.Modal(document.getElementById('modalViewTimecard'));
  const editModal = new bootstrap.Modal(document.getElementById('modalEditTimecard'));
  const editModalNote = new bootstrap.Modal(document.getElementById('modalEditTimecardNote'));
  const viewTimecardForm = document.getElementById('viewTimecardForm');
  const editTimecardForm = document.getElementById('editTimecardForm');
  const editTimecardNoteForm = document.getElementById('editTimecardNoteForm');
  recalc.addEventListener('click', async function () {
    let user = USER_ID;
    if (slUser && slUser.value != '') {
      user = slUser.value;
    }
    var date = new Date(monthInput.value);
    const year = date.getFullYear();
    const month = date.getMonth() + 1;
    recalculation(user, year, month);
  });

  document.getElementById('modalViewTimecard').addEventListener('hide.bs.modal', async function () {
    viewTimecardForm.reset();
  });
  document.getElementById('modalEditTimecard').addEventListener('hide.bs.modal', async function () {
    editTimecardForm.reset();
  });
  document.getElementById('modalEditTimecardNote').addEventListener('hide.bs.modal', async function () {
    editTimecardNoteForm.reset();
  });

  document.querySelector('.datatables-timecard').addEventListener('click', async function (e) {
    if (e.target.closest('[data-checkin]')) {
      e.preventDefault();
      const owner = e.target.dataset.owner;
      checkin(owner);
    }
    if (e.target.closest('[data-checkout]')) {
      e.preventDefault();
      const id = e.target.dataset.id;
      const open = e.target.dataset.open;
      const owner = e.target.dataset.owner;
      checkout(id, open, owner);
    }

    if (e.target.closest('.item-view')) {
      e.preventDefault();
      const date = e.target.closest('.item-view').dataset.date;
      const userid = e.target.closest('.item-view').dataset.userid;
      const response = await axios.post('/api/index.php?model=timecard&method=get_timecard_by_id', {
        date: date,
        userid: userid
      }, {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        }
      });
      if (response.status === 200 && response.data && response.data.status === 'success') {
        // Open the modal
        var timecardinfo = response.data.data;
        document.getElementById('modalViewTimecardTitle').innerHTML = findUsername(userid);
        if (timecardinfo.id) {
          document.getElementById('viewTimecardId').value = timecardinfo.id;
        }
        document.getElementById('viewTimecardDate').value = date;
        if (timecardinfo.timecard_open) {
          document.getElementById('viewTimecardOpen').value = timecardinfo.timecard_open;
        }
        if (timecardinfo.timecard_close) {
          document.getElementById('viewTimecardClose').value = timecardinfo.timecard_close;
        }
        if (timecardinfo.timecard_comment) {
          document.getElementById('viewTimecardNote').value = decodeHtmlEntities(timecardinfo.timecard_comment);
        }


        document.getElementById('viewTimecardOriginOpenText').style.display = 'none';
        document.getElementById('viewTimecardOriginalCloseText').style.display = 'none';

        if (timecardinfo.timecard_originalopen) {
          if (timecardinfo.timecard_originalopen != '' && timecardinfo.timecard_originalopen != timecardinfo.timecard_open) {
            document.getElementById('viewTimecardOriginOpenText').style.display = 'block';
            document.getElementById('viewTimecardOriginOpen').innerHTML = timecardinfo.timecard_originalopen;
          }
        }
        if (timecardinfo.timecard_originalclose) {
          if (timecardinfo.timecard_originalclose != '' && timecardinfo.timecard_originalclose != timecardinfo.timecard_close) {
            document.getElementById('viewTimecardOriginalCloseText').style.display = 'block';
            document.getElementById('viewTimecardOriginalClose').innerHTML = timecardinfo.timecard_originalclose;
          }
        }

        if (timecardinfo.editor && timecardinfo.editor != '') {
          document.getElementById('viewTimecardLastEdit').style.display = 'block';
          document.getElementById('viewTimecardLastEditTime').innerHTML = findUsername(timecardinfo.editor) + ' ' + timecardinfo.updated;
        } else {
          document.getElementById('viewTimecardLastEdit').style.display = 'none';
        }

        viewModal.show();
      } else {
        showMessage(response.data.error, true);
      }
    }

    if (e.target.closest('.item-edit')) {
      e.preventDefault();
      const date = e.target.closest('.item-edit').dataset.date;
      const userid = e.target.closest('.item-edit').dataset.userid;
      const response = await axios.post('/api/index.php?model=timecard&method=get_timecard_by_id', {
        date: date,
        userid: userid
      }, {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        }
      });
      if (response.status === 200 && response.data && response.data.status === 'success') {
        // Open the modal
        var timecardinfo = response.data.data;
        document.getElementById('modalEditTimecardTitle').innerHTML = findUsername(userid);
        if (timecardinfo.id) {
          document.getElementById('editTimecardId').value = timecardinfo.id;
        }
        document.getElementById('editTimecardDate').value = date;
        document.getElementById('editTimecardUserid').value = userid;
        if (timecardinfo.timecard_open) {
          editTimecardOpen.setDate(timecardinfo.timecard_open);
        }
        if (timecardinfo.timecard_close) {
          editTimecardClose.setDate(timecardinfo.timecard_close);
        }
        if (timecardinfo.timecard_comment) {
          document.getElementById('editTimecardNote').value = decodeHtmlEntities(timecardinfo.timecard_comment);
        }

        editModal.show();
      } else {
        showMessage(response.data.message_code, true);
      }
    }

    if (e.target.closest('.item-note')) {
      e.preventDefault();
      const date = e.target.closest('.item-note').dataset.date;
      const userid = e.target.closest('.item-note').dataset.userid;
      const response = await axios.post('/api/index.php?model=timecard&method=get_timecard_by_id', {
        date: date,
        userid: userid
      }, {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        }
      });
      if (response.status === 200 && response.data && response.data.status === 'success') {
        // Open the modal
        var timecardinfo = response.data.data;
        document.getElementById('modalEditTimecardNoteTitle').innerHTML = findUsername(userid);
        if (timecardinfo.id) {
          document.getElementById('editTimecardNoteId').value = timecardinfo.id;
        }
        document.getElementById('editTimecardNoteDate').value = date;
        document.getElementById('editTimecardNoteUserid').value = userid;
        if (timecardinfo.timecard_comment) {
          document.getElementById('editTimecardNoteNote').value = decodeHtmlEntities(timecardinfo.timecard_comment);
        }

        editModalNote.show();
      } else {
        showMessage(response.data.message_code, true);
      }
    }

  });

  

  const fvEdit = FormValidation.formValidation(editTimecardForm, {
    fields: {
      timecard_open: {
        validators: {
          stringLength: {
            min: 4,
            message: '4文字以上入力してください'
          },
          regex: {
            regex: /^[0-9]{2}:[0-9]{2}$/,
            message: '時間を正しく入力してください'
          }
        }
      },
      timecard_close: {
        validators: {
          stringLength: {
            min: 4,
            message: '4文字以上入力してください'
          },
          regex: {
            regex: /^[0-9]{2}:[0-9]{2}$/,
            message: '時間を正しく入力してください'
          }
        }
      },
      timecard_comment: {
        validators: {
          stringLength: {
            min: 4,
            message: '4文字以上入力してください'
          }
        }
      },
    },
    plugins: {
      trigger: new FormValidation.plugins.Trigger(),
      bootstrap5: new FormValidation.plugins.Bootstrap5({
        eleValidClass: '',
        rowSelector: '.form-control-validation'
      }),
    },
  });

  editTimecardForm.addEventListener('submit', function (e) {
    e.preventDefault();
    fvEdit.validate().then(function (status) {
      if (status === 'Valid') {
        displayHourglass();
        const formData = new FormData(editTimecardForm);
        axios.post('/api/index.php?model=timecard&method=edit_timecard', formData)
          .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
              showMessage('タイムカードを編集しました');
              changeData();
            } else {
              if (response.data.message_code) {
                showMessage(response.data.message_code, true);
              } else {
                showMessage('タイムカードを編集できませんでした', true);
              }
            }
            $('#modalEditTimecard').modal('hide');
          })
          .catch(function (error) {
            handleErrors(error);
            $('#modalEditTimecard').modal('hide');
          });
      }
    });
  });

  const fvEditNote = FormValidation.formValidation(editTimecardNoteForm, {
    fields: {
      timecard_comment: {
        validators: {
          stringLength: {
            min: 4,
            message: '4文字以上入力してください'
          },
          regex: {
            regex: /^[0-9]{2}:[0-9]{2}$/,
            message: '時間を正しく入力してください'
          }
        }
      },
    },
    plugins: {
      trigger: new FormValidation.plugins.Trigger(),
      bootstrap5: new FormValidation.plugins.Bootstrap5({
        eleValidClass: '',
        rowSelector: '.form-control-validation'
      }),
    },
  });

  editTimecardNoteForm.addEventListener('submit', function (e) {
    e.preventDefault();
    fvEditNote.validate().then(function (status) {
      if (status === 'Valid') {
        displayHourglass();
        const formData = new FormData(editTimecardNoteForm);
        axios.post('/api/index.php?model=timecard&method=edit_timecard_note', formData)
          .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
              showMessage('タイムカードの備考を編集しました');
              changeData();
            } else {
              if (response.data.message_code) {
                showMessage(response.data.message_code, true);
              } else {
                showMessage('タイムカードの備考を編集できませんでした', true);
              }
            }
            $('#modalEditTimecardNote').modal('hide');
          })
          .catch(function (error) {
            handleErrors(error);
            $('#modalEditTimecardNote').modal('hide');
          });
      }
    });
  });


 
}

async function recalculation(user, year, month) {
  displayHourglass();
  const response = await axios.get(`/api/index.php?model=timecard&method=recalculateApi&member=${user}&year=${year}&month=${month}`);
  // check if the response is successful
  if (response.status !== 200 || !response.data || !response.data.list) {
    handleErrors(response.data);
    return;
  }

  handleSuccess(response.data.message_code);
  changeData();


}


function checkin(owner = '') {
  displayHourglass();
  axios({
    method: 'post',
    url: '/api/index.php?model=timecard&method=checkin',
    data: {
      owner: owner
    },
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    }
  })
    .then(function (response) {
      if (response.status === 200 && response.data && response.data.status === 'success') {
        handleSuccess(response.data.message_code);
        // delete row from datatable
        changeData();
      } else {
        handleErrors(response.data.message_code);
      }
    }).catch(function (error) {
      handleErrors(error);
      console.log(error, error.response);
    });
}

function checkout(id = '', open = '', owner = '') {
  displayHourglass();
  axios({
    method: 'post',
    url: '/api/index.php?model=timecard&method=checkout',
    data: {
      id: id,
      open: open,
      owner: owner
    },
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    }
  })
    .then(function (response) {
      if (response.status === 200 && response.data && response.data.status === 'success') {
        handleSuccess(response.data.message_code);
        // delete row from datatable
        changeData();
      } else {
        handleErrors(response.data.message_code);
      }
    }).catch(function (error) {
      handleErrors(error);
      console.log(error, error.response);
    });
}



async function changeData() {
  displayHourglass();
  const slUser = document.getElementById('selectpickerUser');
  const monthInput = document.getElementById('timecard-month-input');

  let user = USER_ID;
  if (slUser && slUser.value != '') {
    user = slUser.value;
  }
  var date = new Date(monthInput.value);
  const year = date.getFullYear();
  const month = date.getMonth() + 1;
  const data = await get_timecard(user, year, month);
  drawTable(data);
  hideHourglass();
}

//add event listener for selectpicker
async function initTable() {
  changeData();
}


function drawTable(data) {
  if (dt_table) {
    dt_table.clear().rows.add(Object.values(data)).draw();
  }
}

document.addEventListener('DOMContentLoaded', function () {
  
  flatpickr("#timecard-month-input", {
    plugins: [new monthSelectPlugin({
      monthSelector: true,
      dateFormat: "Y-m",
      locale: "ja",
    })],
    dateFormat: "Y-m",
    locale: "ja"
  });
  const dt_timecard_table = document.querySelector('.datatables-timecard');
  const monthInput = document.getElementById('timecard-month-input');
  var startDate = $('html').attr('data-timecard-start');
  const today = new Date();
  if (today.getDate() >= startDate && startDate != '1') {
    today.setMonth(today.getMonth() + 1);
  }
  // Format the current month as YYYY-MM
  const currentMonth = today.toISOString().slice(0, 7);
  // Set the default value to the current month
  monthInput.value = currentMonth;

  // Set the max attribute to disallow future months
  monthInput.max = currentMonth;
  // Variable declaration for table

  // Users datatable
  if (dt_timecard_table) {
    dt_table = new DataTable(dt_timecard_table, {
      paging: false,
      searching: false,
      info: false,
      ordering: false,
      columns: [
        { data: 'timecard_date', title: '日付' },
        { data: 'timecard_open', title: '出社' },
        { data: 'timecard_close', title: '退社' },
        { data: 'timecard_time', title: '勤務時間' },
        { data: 'timecard_timeover', title: '時間外' },
        { data: 'timecard_timeinterval', title: '休憩時間' },
        { data: 'timecard_timeholiday', title: '休日出勤' },
        { data: 'timecard_comment', title: '備考' },
        { data: 'id', title: 'ID', visible: false },
        { data: 'owner', title: 'Owner', visible: false },
        { title: '操作', visible: true },
      ],
      columnDefs: [
        {
          // Add custom rendering for the "出社" column
          targets: 1,
          render: function (data, type, full, meta) {
            if (today.getDate() == full.timecard_day && isSameUser) {
              if (!data) {
                return `<button type="button" class="btn btn-primary btn-sm" data-id="${full.id}" data-owner="${full.owner}" data-checkin>出社</button>`;
              }
            }
            if (full.timecard_originalopen != '' && full.timecard_originalopen != full.timecard_open) {
              return `<span class="badge bg-label-danger">${data}</span>`;
            }
            return data;
          }
        },
        {
          // Add custom rendering for the "出社" column
          targets: 2,
          render: function (data, type, full, meta) {
            if (today.getDate() == full.timecard_day && isSameUser) {
              if (!data && full.timecard_open) {
                return `<button type="button" class="btn btn-primary btn-sm" data-id="${full.id}" data-owner="${full.owner}" data-open="${full.timecard_open}" data-checkout>退社</button>`;
              }
            }
            if (full.timecard_originalclose != '' && full.timecard_originalclose != full.timecard_close) {
              return `<span class="badge bg-label-danger">${data}</span>`;
            }
            return data;
          }
        },
        {
          // Add custom rendering for the "日付" column
          targets: 0,
          render: function (data, type, full, meta) {
            var date = new Date(data);
            var day = date.getDate();
            var month = date.getMonth() + 1;
            var dayWeek = date.getDay();
            var dayWeekName = ['日', '月', '火', '水', '木', '金', '土'];
            if (dayWeek == 6) {
              return `<span class="badge bg-label-warning day-label">${dayWeekName[dayWeek]}</span>&nbsp;${month}/${day}`;
            }
            if (dayWeek == 0) {
              return `<span class="badge bg-label-danger day-label">${dayWeekName[dayWeek]}</span>&nbsp;${month}/${day}`;
            }
            return `<span class="badge bg-label-primary day-label">${dayWeekName[dayWeek]}</span>&nbsp;${month}/${day}`;
          }
        },
        {
          targets: -1,
          searchable: false,
          orderable: false,
          render: (data, type, full, meta) => {
            return `
              <div class="d-flex align-items-center justify-content-start">
                <a href="javascript:;" data-date="${full['timecard_date']}" data-userid="${full['owner']}" class="btn btn-text-secondary rounded-pill waves-effect btn-icon item-view"><i class="icon-base ti tabler-eye me-0 me-sm-1"></i></a>
                <a href="javascript:;" data-date="${full['timecard_date']}" data-userid="${full['owner']}" class="btn btn-text-secondary rounded-pill waves-effect btn-icon item-note"><i class="icon-base ti tabler-edit me-0 me-sm-1"></i></a>
                ${user_role != 'member' ? `<a href="javascript:;" class="btn btn-text-secondary rounded-pill waves-effect btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                    <i class="icon-base ti tabler-dots-vertical icon-22px"></i>
                  </a>
                  <div class="dropdown-menu dropdown-menu-end m-0">
                  <a href="javascript:;" data-date="${full['timecard_date']}" data-userid="${full['owner']}" class="dropdown-item item-edit"><i class="icon-base ti tabler-pencil me-0 me-sm-1 icon-16px"></i> 編集</a>
                  </div>` : ''}
              </div>
              `;
          }
        },
        {
          targets: 7,
          orderable: false,
          render: (data, type, full, meta) => {
            var dayString = full.timecard_date.split('-');
            var xdayString = dayString[0] + '-' + addLeadingZero(dayString[1]) + '-' + addLeadingZero(dayString[2]);
            if (holidayList.includes(xdayString)) {
              return `<span class="badge bg-label-warning me-1">休日</span>`+ decodeHtmlEntities(data);
            } else{
              return decodeHtmlEntities(data);
            }
          }
        }
      ],
      // format row color if sat or sun
      createdRow: function (row, data, dataIndex) {
        var dayString = data.timecard_date.split('-');
        var xdayString = dayString[0] + '-' + addLeadingZero(dayString[1]) + '-' + addLeadingZero(dayString[2]);

        var date = new Date(data.timecard_date);
        var dayWeek = date.getDay();
        if (dayWeek === 0 || dayWeek === 6) {
          $(row).addClass('table-warning');
        }
        if (holidayList.includes(xdayString)) {
          $(row).addClass('table-danger');
          $(row).find('.day-label').addClass('bg-label-warning');
        }
      },
      order: [[0, 'asc']], // Order by the first column (Date) in ascending order
      language: {
        zeroRecords: 'データがありません',
        emptyTable: 'データがありません',
      }
    });
    fetchGroup();
    initTable();
    addEvent();
  }
});