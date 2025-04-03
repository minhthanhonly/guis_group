'use strict';

var isSameUser = false;
var dt_table = null
var timecard_start_date = $('html').attr('data-timecard-start');
var sum = {
  timecard_time: 0,
  timecard_timeover: 0,
  timecard_timeholiday: 0,
  work_days: 0,
  total_days: 0
}
var holidayList = [];

function updateAnalytics(data){
  const timecard_time = document.getElementById('work_time');
  const timecard_timeover = document.getElementById('over_time');
  const timecard_timeholiday = document.getElementById('holiday_time');
  const work_days = document.getElementById('work_days');

  timecard_time.innerHTML = formatTime(data.timecard_time);
  timecard_timeover.innerHTML = formatTime(data.timecard_timeover);
  timecard_timeholiday.innerHTML = formatTime(data.timecard_timeholiday);
  work_days.innerHTML = `${data.work_days} / ${data.total_days}`;
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
  if(timecard_start_date != '1'){
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
      if(foundItem.holiday == 1){
        foundItem.timecard_timeinterval = '';
        foundItem.timecard_timeover = '';
        foundItem.timecard_timeholiday = foundItem.timecard_time;
        foundItem.timecard_time = '';
        var timeHoliday = foundItem.timecard_timeholiday.split(':');
        sum.timecard_timeholiday += parseInt(timeHoliday[0]) * 60 + parseInt(timeHoliday[1]);
      } else{
        foundItem.timecard_timeholiday = '';
      }
      days[i] = foundItem;

      if(foundItem.timecard_open){
        countWorkDays++;
      }

      if(foundItem.timecard_time){
        var time = foundItem.timecard_time.split(':');
        sum.timecard_time += parseInt(time[0]) * 60 + parseInt(time[1]);
      }
      if(foundItem.timecard_timeover){
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
  const response = await axios.get(`/api/index.php?model=user&method=index&group=all&type=active`);
  // check if the response is successful
  if (response.status !== 200 || !response.data || !response.data.list) {
    handleErrors(response.data);
  }
  // loop through the day in that month, check if not exist then add it
  const list = response.data.list;
  return list;
}

async function fetchUser(){
  const slUser = document.getElementById('selectpickerUser');
  slUser.innerHTML = '';
  const userList = await get_users();
  const currentUser = slUser.getAttribute('data-current-user');
  userList.forEach(user => {
    // check if the user is the current user
    if (currentUser === user.userid) {
      slUser.innerHTML += `<option data-icon="icon-base ti tabler-user" value="${user.userid}" selected>${user.realname}</option>`;
    } else{
      slUser.innerHTML += `<option data-icon="icon-base ti tabler-user" value="${user.userid}">${user.realname}</option>`;
    }
  });

  // Initialize the selectpicker
  $(slUser).selectpicker('refresh');
  
}

//add event listener for selectpicker
function addEvent(){
  const slUser = document.getElementById('selectpickerUser');
  const monthInput = document.getElementById('timecard-month-input');
  const recalc = document.querySelector('[data-recalculation]');
  // Add event listener for the selectpicker
  slUser.addEventListener('change', async function () {
    changeData()
  });

  // Add event listener for the month input
  monthInput.addEventListener('change', async function () {
    changeData()
  });

  recalc.addEventListener('click', async function () {
    const user = slUser.value;
    var date = new Date(monthInput.value);
    const year = date.getFullYear();
    const month = date.getMonth() + 1;
    recalculation(user, year, month);
  });

  document.querySelector('.datatables-timecard').addEventListener('click', function (e) {
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

     // if (e.target.closest('.item-edit')) {
    //   e.preventDefault();

    //   // Get the row data (assuming DataTable is used)
    //   const row = dt_table.row(e.target.closest('tr')).data();

    //   // Populate the modal with the row data
    //   document.querySelector('#modalEditID').value = row.id;
    //   document.querySelector('#modalEditDate').value = row.date;
    //   document.querySelector('#modalEditDateOld').value = row.date;
    //   document.querySelector('#modalEditName').value = row.name;

    //   // Open the modal
    //   const editModal = new bootstrap.Modal(document.getElementById('editRoleModal'));
    //   editModal.show();
    // }
  });
}

 async function recalculation(user, year, month){
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
      _log(response)
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
      _log(response)
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



async function changeData(){
  displayHourglass();
  const slUser = document.getElementById('selectpickerUser');
  const monthInput = document.getElementById('timecard-month-input');

  const user = slUser.value;
  var date = new Date(monthInput.value);
  const year = date.getFullYear();
  const month = date.getMonth() + 1;
  const data = await get_timecard(user, year, month);
  drawTable(data);
  hideHourglass();
}

//add event listener for selectpicker
async function initTable(){
  changeData();
}


function drawTable(data){
  if (dt_table) {
    dt_table.clear().rows.add(Object.values(data)).draw();
  }
}

document.addEventListener('DOMContentLoaded', function () {
  const dt_timecard_table = document.querySelector('.datatables-timecard');
  const monthInput = document.getElementById('timecard-month-input');

  const today = new Date();
  if(today.getDate() >= 21){
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
        { data: 'owner', title: 'Owner', visible: true },
      ],
      columnDefs: [
        {
          // Add custom rendering for the "出社" column
          targets: 1,
          render: function (data, type, full, meta) {
            if(today.getDate() == full.timecard_day && isSameUser){
              if(!data){
                return `<button type="button" class="btn btn-primary btn-sm" data-id="${full.id}" data-owner="${full.owner}" data-checkin>出社</button>`;
              }
            }
            return data;
          }
        },
        {
          // Add custom rendering for the "出社" column
          targets: 2,
          render: function (data, type, full, meta) {
            if(today.getDate() == full.timecard_day && isSameUser){
              if(!data && full.timecard_open){
                return `<button type="button" class="btn btn-primary btn-sm" data-id="${full.id}" data-owner="${full.owner}" data-open="${full.timecard_open}" data-checkout>退社</button>`;
              }
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
            var dayWeekName = ['日','月', '火', '水', '木', '金', '土'];
            if(dayWeek == 6){
              return `<span class="badge bg-label-warning day-label">${dayWeekName[dayWeek]}</span>&nbsp;${month}/${day}`;
            }
            if(dayWeek == 0){
              return `<span class="badge bg-label-danger day-label">${dayWeekName[dayWeek]}</span>&nbsp;${month}/${day}`;
            }
            return `<span class="badge bg-label-primary day-label">${dayWeekName[dayWeek]}</span>&nbsp;${month}/${day}`;
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
        if(holidayList.includes(xdayString)){
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
    fetchUser();
    initTable();
    addEvent();
  }
});