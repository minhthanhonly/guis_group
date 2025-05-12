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
    const eventEndDate = document.getElementById('eventEndDate');
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
        enableTime: true,
        altFormat: 'Y-m-dTH:i:S',
        defaultHour: '09:00',
        time_24hr: true,
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
        enableTime: true,
        altFormat: 'Y-m-dTH:i:S',
        defaultHour: '09:00',
        time_24hr: true,
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

      eventTitle.value = eventToUpdate.title;
      // start.setDate(eventToUpdate.start, true, 'Y-m-d H:i');
      // eventToUpdate.end !== null
      // ? end.setDate(eventToUpdate.end, true, 'Y-m-d H:i')
      // : end.setDate(eventToUpdate.start, true, 'Y-m-d H:i');

      if(eventToUpdate.allDay == true){
        // set flatpickr to all day
        start.setDate(eventToUpdate.start, false, 'Y-m-d');
        end.setDate(eventToUpdate.end, false, 'Y-m-d');
        start.set('enableTime', false);
        end.set('enableTime', false);
        start.redraw();
        end.redraw();
      }
      // } else{
      //   start.setDate(eventToUpdate.start, true, 'Y-m-d H:i');
      //   end.setDate(eventToUpdate.end, true, 'Y-m-d H:i');
      //   start.set('enableTime', true);
      //   end.set('enableTime', true);
      // }
      eventToUpdate.allDay == true ? (allDaySwitch.checked = true) : (allDaySwitch.checked = false);
      eventLabel.val(eventToUpdate.extendedProps.calendar).trigger('change');
      eventToUpdate.extendedProps.comment != undefined
        ? (eventComment.value = eventToUpdate.extendedProps.comment)
        : null;
      eventToUpdate.extendedProps.public != undefined
        ? (eventPublic.value = eventToUpdate.extendedProps.public)
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
      let startDate = new Date(eventStartDate.value);
      let endDate = new Date(eventEndDate.value);
      if(allDaySwitch.checked){
        eventStartDate.value = moment(startDate).format('YYYY-MM-DD');
        eventEndDate.value = moment(endDate).format('YYYY-MM-DD');
      } else{
        start.setDate(startDate, true, 'Y-m-d H:i');
        end.setDate(endDate, true, 'Y-m-d H:i');
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

      // eventDataTransform: function(event) {
      //   if (event.allDay && event.end) {
      //       event.end = new Date(event.end.getTime() - 24 * 60 * 60 * 1000);
      //   }
      //   return event;
      // },
    
      
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
        eventStartDate.value = date;
        eventEndDate.value = date;
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
        eventStartDate: {
          validators: {
            notEmpty: {
              message: 'Please enter start date '
            }
          }
        },
        eventEndDate: {
          validators: {
            notEmpty: {
              message: 'Please enter end date '
            }
          }
        }
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
    function addEvent(eventData) {
      // ? Add new event data to current events object and refetch it to display on calender
      // ? You can write below code to AJAX call success response

      currentEvents.push(eventData);
      calendar.refetchEvents();

      // ? To add event directly to calender (won't update currentEvents object)
      // calendar.addEvent(eventData);
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
        public_level: eventData.extendedProps.public_level
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
      // ? Delete existing event data to current events object and refetch it to display on calender
      // ? You can write below code to AJAX call success response
      currentEvents = currentEvents.filter(function (event) {
        return event.id != eventId;
      });
      calendar.refetchEvents();

      // ? To delete event directly to calender (won't update currentEvents object)
      // removeEventInCalendar(eventId);
    }

    // (Update Event In Calendar (UI Only)
    // ------------------------------------------------
    const updateEventInCalendar = (updatedEventData, propsToUpdate, extendedPropsToUpdate) => {
      const existingEvent = calendar.getEventById(updatedEventData.id);

      // --- Set event properties except date related ----- //
      // ? Docs: https://fullcalendar.io/docs/Event-setProp
      // dateRelatedProps => ['start', 'end', 'allDay']
      // eslint-disable-next-line no-plusplus
      for (var index = 0; index < propsToUpdate.length; index++) {
        var propName = propsToUpdate[index];
        existingEvent.setProp(propName, updatedEventData[propName]);
      }

      // --- Set date related props ----- //
      // ? Docs: https://fullcalendar.io/docs/Event-setDates
      existingEvent.setDates(updatedEventData.start, updatedEventData.end, {
        allDay: updatedEventData.allDay
      });

      // --- Set event's extendedProps ----- //
      // ? Docs: https://fullcalendar.io/docs/Event-setExtendedProp
      // eslint-disable-next-line no-plusplus
      for (var index = 0; index < extendedPropsToUpdate.length; index++) {
        var propName = extendedPropsToUpdate[index];
        existingEvent.setExtendedProp(propName, updatedEventData.extendedProps[propName]);
      }
    };

    // Remove Event In Calendar (UI Only)
    // ------------------------------------------------
    function removeEventInCalendar(eventId) {
      calendar.getEventById(eventId).remove();
    }

    // Add new event
    // ------------------------------------------------
    btnSubmit.addEventListener('click', e => {
      if (btnSubmit.classList.contains('btn-add-event')) {
        if (isFormValid) {
          let newEvent = {
            id: calendar.getEvents().length + 1,
            title: eventTitle.value,
            start: eventStartDate.value,
            end: eventEndDate.value,
            startStr: eventStartDate.value,
            endStr: eventEndDate.value,
            display: 'block',
            extendedProps: {
              location: eventLocation.value,
              guests: eventGuests.val(),
              calendar: eventLabel.val(),
              description: eventDescription.value
            }
          };
        
          if (allDaySwitch.checked) {
            newEvent.allDay = true;
          }
          addEvent(newEvent);
          bsAddEventSidebar.hide();
        }
      } else {
        // Update event
        // ------------------------------------------------
        if (isFormValid) {
          let eventData = {
            id: eventToUpdate.id,
            title: eventTitle.value,
            start: eventStartDate.value,
            end: eventEndDate.value,
            url: eventUrl.value,
            extendedProps: {
              location: eventLocation.value,
              guests: eventGuests.val(),
              calendar: eventLabel.val(),
              description: eventDescription.value
            },
            display: 'block',
            allDay: allDaySwitch.checked ? true : false
          };

          updateEvent(eventData);
          bsAddEventSidebar.hide();
        }
      }
    });

    // Call removeEvent function
    btnDeleteEvent.addEventListener('click', e => {
      removeEvent(parseInt(eventToUpdate.id));
      // eventToUpdate.remove();
      bsAddEventSidebar.hide();
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
