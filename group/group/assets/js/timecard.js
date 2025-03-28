'use strict';
// Add a custom addDays method to the Date prototype
Date.prototype.addDays = function (days) {
  const date = new Date(this.valueOf());
  date.setDate(date.getDate() + days);
  return date;
};
var isSameUser = false;
var sum = {
  timecard_time: 0,
  timecard_timeover: 0,
  timecard_timeholiday: 0,
  work_days: 0,
  total_days: 0
}
var holidayList = [];

function formatTime(time) {
  var hour = parseInt(time / 60) < 10 ? `0${parseInt(time / 60)}` : `${parseInt(time / 60)}`;
  var minute = time % 60 < 10 ? `0${time % 60}` : `${time % 60}`;
  return `${hour}:${minute}`;
}

function handleErrors(response) {
  Swal.fire({
    title: 'Error!',
    text: response,
    icon: 'error',
    customClass: {
      confirmButton: 'btn btn-primary'
    },
    buttonsStyling: false
  })
  hideHourglass();
  throw new Error('Failed to fetch data');
}

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

function addLeadingZero(number){
  number = parseInt(number);
  return number < 10 ? `0${number}` : `${number}`;
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
  let startMonth = month - 2;
  let startYear = year;
  if(startMonth < 0) {
    startYear = year - 1;
    startMonth = 11
  }
  let start = new Date(startYear, startMonth, 21);
  let end = new Date(year, month - 1, 20);
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
function addEvent(dt_user){
  const slUser = document.getElementById('selectpickerUser');
  const monthInput = document.getElementById('timecard-month-input');
  // Add event listener for the selectpicker
  slUser.addEventListener('change', async function () {
    changeData(dt_user)
  });

  // Add event listener for the month input
  monthInput.addEventListener('change', async function () {
    changeData(dt_user)
  });
}

async function changeData(dt_user){
  displayHourglass();
  const slUser = document.getElementById('selectpickerUser');
  const monthInput = document.getElementById('timecard-month-input');

  const user = slUser.value;
  var date = new Date(monthInput.value);
  const year = date.getFullYear();
  const month = date.getMonth() + 1;
  const data = await get_timecard(user, year, month);
  drawTable(dt_user, data);
  hideHourglass();
}

//add event listener for selectpicker
async function initTable(dt_user){
  changeData(dt_user);
}


function drawTable(dt_user, data){
  if (dt_user) {
    dt_user.clear().rows.add(Object.values(data)).draw();
  }
}

document.addEventListener('DOMContentLoaded', function () {
  var dt_user;
  const dt_user_table = document.querySelector('.datatables-users');
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
  if (dt_user_table) {
    dt_user = new DataTable(dt_user_table, {
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
      ],
      columnDefs: [
        {
          // Add custom rendering for the "出社" column
          targets: 1,
          render: function (data, type, full, meta) {
            if(today.getDate() == full.timecard_day && isSameUser){
              if(!data){
                return `<button type="button" class="btn btn-primary btn-sm" data-date="${full.timecard_date}" data-owner="${full.owner}" data-checkin>出社</button>`;
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
                return `<button type="button" class="btn btn-primary btn-sm" data-date="${full.timecard_date}" data-owner="${full.owner}" data-checkout>退社</button>`;
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
    initTable(dt_user);
    addEvent(dt_user);
  }
});