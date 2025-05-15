/**
 * App Calendar
 */

/**
 * ! If both start and end dates are same Full calendar will nullify the end date value.
 * ! Full calendar will end the event on a day before at 12:00:00AM thus, event won't extend to the end date.
 * ! We are getting events from a separate file named app-calendar-events.js. You can add or remove events from there.
 *
 **/

'use strict';

document.addEventListener('DOMContentLoaded', async function () {
  const direction = isRtl ? 'rtl' : 'ltr';
  (async function () {
    // DOM Elements
    const calendarEl = document.getElementById('calendar');
    const appCalendarSidebar = document.querySelector('.app-calendar-sidebar');
    const addEventSidebar = document.getElementById('addEventSidebar');
    const appOverlay = document.querySelector('.app-overlay');
    const offcanvasTitle = document.querySelector('.offcanvas-title');
    const btnToggleSidebar = document.querySelector('.btn-toggle-sidebar');
    const btnSubmit = document.getElementById('addEventBtn');
    const btnDeleteEvent = document.querySelector('.btn-delete-event');
    const btnCancel = document.querySelector('.btn-cancel');
    const eventTitle = document.getElementById('eventTitle');
    const eventStartDate = document.getElementById('eventStartDate');
    const eventStartDate2 = document.getElementById('eventStartDate2');
    const eventEndDate = document.getElementById('eventEndDate');
    const eventEndDate2 = document.getElementById('eventEndDate2');
    const eventStartDateDiv = document.getElementById('eventStartDateDiv');
    const eventEndDateDiv = document.getElementById('eventEndDateDiv');
    const eventStartDateDiv2 = document.getElementById('eventStartDateDiv2');
    const eventEndDateDiv2 = document.getElementById('eventEndDateDiv2');
    const eventEndDateHidden = document.getElementById('eventEndDateHidden');
    const eventPublic = document.getElementById('eventPublic');
    const eventComment = document.getElementById('eventComment');
    const allDaySwitch = document.querySelector('.allDay-switch');
    const selectAll = document.querySelector('.select-all');
    const eventLastUpdate = document.getElementById('eventLastUpdate');
    const eventLastUpdateTime = document.getElementById('eventLastUpdateTime');
    const filterInputs = Array.from(document.querySelectorAll('.input-filter'));
    const eventBtn = document.getElementById('eventBtn');

    // Calendar settings
    const calendarColors = {
      '仕事': 'primary',
      '勤怠': 'warning',
      '休日': 'danger',
      '個人': 'info',
      'その他': 'success'
    };

    // External jQuery Elements
    const eventLabel = $('#eventLabel'); // ! Using jQuery vars due to select2 jQuery dependency

    let eventList = [];
    let forceUpdate = false;
    async function getEventList(start, end){
      eventList = [];
      const response = await axios.get(`/api/index.php?model=schedule&method=get_event&start=${start}&end=${end}`);
      // check if the response is successful
      if (response.status !== 200 || !response.data) {
        handleErrors(response.data);
      }
      return response.data;
    }


    // Event Data
    let currentEvents = eventList; // Assuming events are imported from app-calendar-events.js
    let isFormValid = false;
    let eventToUpdate = null;

    // Offcanvas Instance
    const bsAddEventSidebar = new bootstrap.Offcanvas(addEventSidebar);


    if (eventLabel.length) {
      function renderBadges(option) {
        if (!option.id) {
          return option.text;
        }
        var $badge =
          "<span class='badge badge-dot bg-" + $(option.element).data('label') + " me-2'> " + '</span>' + option.text;

        return $badge;
      }
      eventLabel.wrap('<div class="position-relative"></div>').select2({
        placeholder: 'Select value',
        dropdownParent: eventLabel.parent(),
        templateResult: renderBadges,
        templateSelection: renderBadges,
        minimumResultsForSearch: -1,
        escapeMarkup: function (es) {
          return es;
        }
      });
    }

    // Render guest avatars
    // if (eventGuests.length) {
    //   function renderGuestAvatar(option) {
    //     if (!option.id) return option.text;
    //     return `
    // <div class='d-flex flex-wrap align-items-center'>
    //   <div class='avatar avatar-xs me-2'>
    //     <img src='${assetsPath}img/avatars/${$(option.element).data('avatar')}'
    //       alt='avatar' class='rounded-circle' />
    //   </div>
    //   ${option.text}
    // </div>`;
    //   }
    //   eventGuests.wrap('<div class="position-relative"></div>').select2({
    //     placeholder: 'Select value',
    //     dropdownParent: eventGuests.parent(),
    //     closeOnSelect: false,
    //     templateResult: renderGuestAvatar,
    //     templateSelection: renderGuestAvatar,
    //     escapeMarkup: function (es) {
    //       return es;
    //     }
    //   });
    // }

    // Event start (flatpicker)
    if (eventStartDate) {
      var start = eventStartDate.flatpickr({
        monthSelectorType: 'static',
        static: true,
        enableTime: false,
        altFormat: 'Y-m-d',
        defaultHour: '00:00',
        time_24hr: true,
        locale: 'ja',
        onReady: function (selectedDates, dateStr, instance) {
          if (instance.isMobile) {
            instance.mobileInput.setAttribute('step', null);
          }
        }
      });
    }

    if (eventStartDate2) {
      var start2 = eventStartDate2.flatpickr({
        monthSelectorType: 'static',
        static: true,
        enableTime: true,
        altFormat: 'Y-m-dTH:i:S',
        defaultHour: '09:00',
        time_24hr: true,
        locale: 'ja',
        onReady: function (selectedDates, dateStr, instance) {
          if (instance.isMobile) {
            instance.mobileInput.setAttribute('step', null);
          }
        }
      });
    }

    // Event end (flatpicker)
    if (eventEndDate) {
      var end = eventEndDate.flatpickr({
        monthSelectorType: 'static',
        static: true,
        enableTime: false,
        altFormat: 'Y-m-d',
        defaultHour: '00:00',
        time_24hr: true,
        locale: 'ja',
        onReady: function (selectedDates, dateStr, instance) {
          if (instance.isMobile) {
            instance.mobileInput.setAttribute('step', null);
          }
        },
        onChange(selectedDates, dateStr, instance) {
          const next = moment(selectedDates[0]).add(1, 'day').format('YYYY-MM-DD');
          eventEndDateHidden.value = next;
        }
      });
    }

    if (eventEndDate2) {
      var end2 = eventEndDate2.flatpickr({
        monthSelectorType: 'static',
        static: true,
        enableTime: true,
        altFormat: 'Y-m-dTH:i:S',
        defaultHour: '09:00',
        time_24hr: true,
        locale: 'ja',
        onReady: function (selectedDates, dateStr, instance) {
          if (instance.isMobile) {
            instance.mobileInput.setAttribute('step', null);
          }
        }
        
      });
    }


    // Event click function
    function eventClick(info) {
      if(info.event.extendedProps.type == 'holiday'){
        return;
      }
      eventToUpdate = info.event;
      if (eventToUpdate.url) {
        info.jsEvent.preventDefault();
        window.open(eventToUpdate.url, '_blank');
      }
      bsAddEventSidebar.show();
      // For update event set offcanvas title text: Update Event
      if (offcanvasTitle) {
        offcanvasTitle.innerHTML = '予定更新';
      }
      btnSubmit.innerHTML = '更新';
      btnSubmit.classList.add('btn-update-event');
      btnSubmit.classList.remove('btn-add-event');
      btnDeleteEvent.classList.remove('d-none');

      eventTitle.value = decodeHtmlEntities(eventToUpdate.title);

      if(eventToUpdate.allDay == true){
        // set flatpickr to all day
        start.setDate(eventToUpdate.start, true, 'Y-m-d');
        const prev = moment(eventToUpdate.end).subtract(1, 'day').format('YYYY-MM-DD');
        end.setDate(prev, true, 'Y-m-d');
        eventEndDateHidden.value = moment(eventToUpdate.end).format('YYYY-MM-DD');
        eventEndDateDiv.classList.remove('d-none');
        eventStartDateDiv.classList.remove('d-none');
        eventEndDateDiv2.classList.add('d-none');
        eventStartDateDiv2.classList.add('d-none');
      } else{
        start2.setDate(eventToUpdate.start, true, 'Y-m-d H:i');
        end2.setDate(eventToUpdate.end, true, 'Y-m-d H:i');
        eventEndDateDiv.classList.add('d-none');
        eventStartDateDiv.classList.add('d-none');
        eventEndDateDiv2.classList.remove('d-none');
        eventStartDateDiv2.classList.remove('d-none');
      }
      eventToUpdate.allDay == true ? (allDaySwitch.checked = true) : (allDaySwitch.checked = false);
      eventLabel.val(eventToUpdate.extendedProps.calendar).trigger('change');
      eventToUpdate.extendedProps.comment != undefined
        ? (eventComment.value = decodeHtmlEntities(eventToUpdate.extendedProps.comment))
        : null;
      eventToUpdate.extendedProps.public_level != undefined
        ? (eventPublic.value = eventToUpdate.extendedProps.public_level)
        : null;
      if(eventToUpdate.extendedProps.editor != ''){
        eventLastUpdate.classList.remove('d-none');
        eventLastUpdateTime.innerHTML = eventToUpdate.extendedProps.editor + ' ' + moment(eventToUpdate.extendedProps.updated).format('YYYY/MM/DD HH:mm');
      } else{
        eventLastUpdate.classList.add('d-none');
      }

      if(eventToUpdate.extendedProps.can_edit == 'true'){
        eventBtn.classList.remove('d-none');
        eventBtn.classList.add('d-flex');
      } else{
        eventBtn.classList.remove('d-flex');
        eventBtn.classList.add('d-none');
      }
    }

    allDaySwitch.addEventListener('change', function(){
      if(allDaySwitch.checked){
        let startDate = new Date(eventStartDate2.value);
        let endDate = new Date(eventEndDate2.value);
        start.setDate(startDate, true, 'Y-m-d');
        end.setDate(endDate, true, 'Y-m-d');
        const next = moment(endDate).add(1, 'day').format('YYYY-MM-DD');
        eventEndDateHidden.value = moment(next).format('YYYY-MM-DD');
        eventEndDateDiv.classList.remove('d-none');
        eventStartDateDiv.classList.remove('d-none');
        eventEndDateDiv2.classList.add('d-none');
        eventStartDateDiv2.classList.add('d-none');
      } else{
        let startDate = new Date(eventStartDate.value);
        let endDate = new Date(eventEndDate.value);
        start2.setDate(startDate, true, 'Y-m-d H:i');
        end2.setDate(endDate, true, 'Y-m-d H:i');
        eventEndDateDiv.classList.add('d-none');
        eventStartDateDiv.classList.add('d-none');
        eventEndDateDiv2.classList.remove('d-none');
        eventStartDateDiv2.classList.remove('d-none');
      }
    });


    // Modify sidebar toggler
    function modifyToggler() {
      const fcSidebarToggleButton = document.querySelector('.fc-sidebarToggle-button');
      fcSidebarToggleButton.classList.remove('fc-button-primary');
      fcSidebarToggleButton.classList.add('d-lg-none', 'd-inline-block', 'ps-0');
      while (fcSidebarToggleButton.firstChild) {
        fcSidebarToggleButton.firstChild.remove();
      }
      fcSidebarToggleButton.setAttribute('data-bs-toggle', 'sidebar');
      fcSidebarToggleButton.setAttribute('data-overlay', '');
      fcSidebarToggleButton.setAttribute('data-target', '#app-calendar-sidebar');
      fcSidebarToggleButton.insertAdjacentHTML(
        'beforeend',
        '<i class="icon-base ti tabler-menu-2 icon-lg text-heading"></i>'
      );
    }

    // Filter events by calender
    function selectedCalendars() {
      let selected = [],
        filterInputChecked = [].slice.call(document.querySelectorAll('.input-filter:checked'));

      filterInputChecked.forEach(item => {
        selected.push(item.getAttribute('data-value'));
      });

      return selected;
    }

    let currentStart = null;

    async function fetchEvents(info, successCallback) {
      if (currentStart === null || moment(info.start).format('YYYY-MM-DD') !== currentStart || forceUpdate) {
        currentStart = moment(info.start).format('YYYY-MM-DD');
        currentEvents = await getEventList(currentStart, moment(info.end).format('YYYY-MM-DD'));
        forceUpdate = false;
      }
      const events = currentEvents;
      let calendars = selectedCalendars();
      let selectedEvents = events.filter(function (event) {
        return calendars.includes(event.extendedProps.calendar.toLowerCase());
      });
      successCallback(selectedEvents);
    }

    // Init FullCalendar
    // ------------------------------------------------
    let calendar = new Calendar(calendarEl, {
      locale: 'ja',
      initialView: 'dayGridMonth',
      events: fetchEvents,
      plugins: [dayGridPlugin, interactionPlugin, listPlugin, timegridPlugin],
      editable: true,
      dragScroll: true,
      dayMaxEvents: 4,
      defaultAllDay: true,
      eventResizableFromStart: true,
      customButtons: {
        sidebarToggle: {
          text: 'Sidebar'
        }
      },
      buttonText: {
        today: '今日',
        month: '月',
        week: '週',
        day: '日',
        list: 'リスト'
      },
      allDayText: '終日',
      firstDay: 1,
      headerToolbar: {
        start: 'sidebarToggle, prev,next, title',
        end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
      },
      direction: direction,
      initialDate: new Date(),
      showNonCurrentDates: false,
      // navLinks: true, // can click day/week names to navigate views
      eventClassNames: function ({ event: calendarEvent }) {
        const colorName = calendarColors[calendarEvent._def.extendedProps.calendar];
        // Background Color
        return ['bg-label-' + colorName];
      },
      
      eventAllow: function(dropInfo, draggedEvent) {
        // If can_edit is 'true', disallow drag/resize
        if (draggedEvent.extendedProps.can_edit != 'true') {
          return false;
        }
        return true;
      },
      
      dateClick: function (info) {
        let date = moment(info.date).format('YYYY-MM-DD');
        resetValues();
        bsAddEventSidebar.show();

        // For new event set offcanvas title text: Add Event
        if (offcanvasTitle) {
          offcanvasTitle.innerHTML = '予定追加';
        }
        btnSubmit.innerHTML = '追加';
        btnSubmit.classList.remove('btn-update-event');
        btnSubmit.classList.add('btn-add-event');
        btnDeleteEvent.classList.add('d-none');
        start.setDate(date, true, 'Y-m-d');
        end.setDate(date, true, 'Y-m-d');
        eventEndDateHidden.value = moment(date).add(1, 'day').format('YYYY-MM-DD');
        eventBtn.classList.add('d-flex');
        eventBtn.classList.remove('d-none');
      },
      eventClick: function (info) {
        eventClick(info);
      },
      viewDidMount: function () {
        modifyToggler();
      },
      
      eventDrop: function(info) {
        if(info.event.extendedProps.can_edit == 'true') {
          updateEvent(info.event);
        }
      },

      eventResize: function(info) {
        if(info.event.extendedProps.can_edit == 'true') {
          updateEvent(info.event);
        }
      },

      eventDidMount: function(info) {
        if (info.event.extendedProps.public_level == 1) {
          const titleEl = info.el.querySelector('.fc-event-title');
          if (titleEl) {
            const badge = document.createElement('span');
            badge.className = 'badge badge-pill bg-label-warning me-1';
            badge.innerHTML = '非公開';
            if (info.view.type === 'listWeek' || info.view.type === 'listMonth') {
              const listEventEl = info.el.querySelector('.fc-list-event-title');
              console.log(listEventEl);
              if (listEventEl) {
                listEventEl.insertAdjacentElement('beforebegin', badge);
              }
            } else {
              titleEl.insertAdjacentElement('beforebegin', badge);
            }
          }
        }
      },
    });


    
    // Render calendar
    calendar.render();

    calendar.on('datesSet', function (info) {
      modifyToggler();
    });
    // Modify sidebar toggler
    modifyToggler();

    const eventForm = document.getElementById('eventForm');
    const fv = FormValidation.formValidation(eventForm, {
      fields: {
        eventTitle: {
          validators: {
            notEmpty: {
              message: 'Please enter event title '
            }
          }
        },
        // eventStartDate: {
        //   validators: {
        //     notEmpty: {
        //       message: 'Please enter start date '
        //     }
        //   }
        // },
        // eventEndDate: {
        //   validators: {
        //     notEmpty: {
        //       message: 'Please enter end date '
        //     }
        //   }
        // }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          // Use this for enabling/changing valid/invalid class
          eleValidClass: '',
          rowSelector: function (field, ele) {
            // field is the field name & ele is the field element
            return '.form-control-validation';
          }
        }),
        submitButton: new FormValidation.plugins.SubmitButton(),
        // Submit the form when all fields are valid
        // defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
        autoFocus: new FormValidation.plugins.AutoFocus()
      }
    })
      .on('core.form.valid', function () {
        // Jump to the next step when all fields in the current step are valid
        isFormValid = true;
      })
      .on('core.form.invalid', function () {
        // if fields are invalid
        isFormValid = false;
      });

    // Sidebar Toggle Btn
    if (btnToggleSidebar) {
      btnToggleSidebar.addEventListener('click', e => {
        btnCancel.classList.remove('d-none');
      });
    }

    // Add Event
    // ------------------------------------------------
    async function addEvent(eventData) {
      var data = {
        title: eventData.title,
        start_date: moment(eventData.start).format('YYYY-MM-DD'),
        start_time: moment(eventData.start).format('HH:mm'),
        end_date: eventData.end != null ? moment(eventData.end).format('YYYY-MM-DD') : "",
        end_time: eventData.end != null ? moment(eventData.end).format('HH:mm') : "",
        allDay: eventData.allDay,
        comment: eventData.extendedProps.comment,
        public_level: eventData.extendedProps.public_level,
        calendar: eventData.extendedProps.calendar
      }

      const response = await axios.post(`/api/index.php?model=schedule&method=add_event`, data,
        {
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          }
        }
      );
      if(response.status !== 200 || response.data.status != 'success') {
        handleErrors(response.data);
      }
      forceUpdate = true;
      calendar.refetchEvents();
    }

    // Update Event
    // ------------------------------------------------
    async function updateEvent(eventData) {
      var data = {
        title: eventData.title,
        start_date: moment(eventData.start).format('YYYY-MM-DD'),
        start_time: moment(eventData.start).format('HH:mm'),
        end_date: eventData.end != null ? moment(eventData.end).format('YYYY-MM-DD') : "",
        end_time: eventData.end != null ? moment(eventData.end).format('HH:mm') : "",
        allDay: eventData.allDay,
        comment: eventData.extendedProps.comment,
        public_level: eventData.extendedProps.public_level,
        calendar: eventData.extendedProps.calendar
      }

      const response = await axios.post(`/api/index.php?model=schedule&method=update_event&id=${eventData.extendedProps.id}`, data,
        {
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          }
        }
      );
      if(response.status !== 200 || response.data.status != 'success') {
        handleErrors(response.data);
      }
      forceUpdate = true;
      calendar.refetchEvents();
    }


    // Remove Event
    // ------------------------------------------------

    function removeEvent(eventId) {
      currentEvents = currentEvents.filter(function (event) {
        return event.extendedProps.id != eventId;
      });
      calendar.refetchEvents();
    }


    // Add new event
    // ------------------------------------------------
    btnSubmit.addEventListener('click', e => {
      if (btnSubmit.classList.contains('btn-add-event')) {
        if (isFormValid) {
          let eventData = {
            title: eventTitle.value,
            allDay: allDaySwitch.checked ? true : false,
            extendedProps: {
              calendar: eventLabel.val(),
              comment: eventComment.value,
              public_level: eventPublic.value
            },
          };

          if(allDaySwitch.checked){
            eventData.start = eventStartDate.value;
            eventData.end = eventEndDateHidden.value;
          } else{
            eventData.start = eventStartDate2.value;
            eventData.end = eventEndDate2.value;
          }
        
          addEvent(eventData);
          bsAddEventSidebar.hide();
        }
      } else {
        // Update event
        // ------------------------------------------------
        if (isFormValid) {
          let eventData = {
            title: eventTitle.value,
            allDay: allDaySwitch.checked ? true : false,
            extendedProps: {
              id: eventToUpdate.extendedProps.id,
              calendar: eventLabel.val(),
              comment: eventComment.value,
              public_level: eventPublic.value
            },
          };

          if(allDaySwitch.checked){
            eventData.start = eventStartDate.value;
            eventData.end = eventEndDateHidden.value;
          } else{
            eventData.start = eventStartDate2.value;
            eventData.end = eventEndDate2.value;
          }

          updateEvent(eventData);
          bsAddEventSidebar.hide();
        }
      }
    });

    // Call removeEvent function
    btnDeleteEvent.addEventListener('click', e => {
      Swal.fire({
        title: '予定を削除しますか？',
        text: '予定を削除すると元に戻すことはできません。',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'はい、削除します',
        cancelButtonText: 'キャンセル',
        customClass: {
          confirmButton: 'btn btn-primary',
          cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
      }).then(function (result) {
        if (result.value) {
          displayHourglass();
          axios({
            method: 'post',
            url: '/api/index.php?model=schedule&method=delete_event',
            data: {
              id: eventToUpdate.extendedProps.id,
            },
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            }
          })
            .then(function (response) {
              if (response.status === 200 && response.data && response.data.status === 'success') {
                showMessage('予定を削除しました');
                removeEvent(parseInt(eventToUpdate.extendedProps.id));
                eventToUpdate.remove();
                bsAddEventSidebar.hide();
              } else {
                showMessage('予定を削除できませんでした', true);
              }
            }).catch(function (error) {
              handleErrors(error);
            });
          
        }
      });
    });

    // Reset event form inputs values
    // ------------------------------------------------
    function resetValues() {
      eventEndDate.value = '';
      eventStartDate.value = '';
      eventTitle.value = '';
      // eventLocation.value = '';
      allDaySwitch.checked = true;
      // eventGuests.val('').trigger('change');
      eventComment.value = '';
      eventPublic.value = '0';
      eventLastUpdate.classList.add('d-none');
      eventBtn.classList.remove('d-flex');
      eventBtn.classList.add('d-none');
      eventEndDateDiv.classList.remove('d-none');
      eventStartDateDiv.classList.remove('d-none');
      eventEndDateDiv2.classList.add('d-none');
      eventStartDateDiv2.classList.add('d-none');
    }

    // When modal hides reset input values
    addEventSidebar.addEventListener('hidden.bs.offcanvas', function () {
      resetValues();
    });

    // Hide left sidebar if the right sidebar is open
    btnToggleSidebar.addEventListener('click', e => {
      if (offcanvasTitle) {
        offcanvasTitle.innerHTML = '予定追加';
      }
      btnSubmit.innerHTML = '追加';
      btnSubmit.classList.remove('btn-update-event');
      btnSubmit.classList.add('btn-add-event');
      btnDeleteEvent.classList.add('d-none');
      appCalendarSidebar.classList.remove('show');
      appOverlay.classList.remove('show');
    });

    // Calender filter functionality
    // ------------------------------------------------
    if (selectAll) {
      selectAll.addEventListener('click', e => {
        if (e.currentTarget.checked) {
          document.querySelectorAll('.input-filter').forEach(c => (c.checked = 1));
        } else {
          document.querySelectorAll('.input-filter').forEach(c => (c.checked = 0));
        }
        calendar.refetchEvents();
      });
    }

    if (filterInputs) {
      filterInputs.forEach(item => {
        item.addEventListener('click', () => {
          document.querySelectorAll('.input-filter:checked').length < document.querySelectorAll('.input-filter').length
            ? (selectAll.checked = false)
            : (selectAll.checked = true);
          calendar.refetchEvents();
        });
      });
    }


  })();
});
