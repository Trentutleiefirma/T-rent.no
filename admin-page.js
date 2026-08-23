(function ($) {
  'use_strict';
  let events = [],
    qtipDescription,
    initialLocaleCode = RNB_CALENDAR.lang_domain
      ? RNB_CALENDAR.lang_domain
      : 'en',
    dayOfWeekStart = RNB_CALENDAR.day_of_week_start
      ? parseInt(RNB_CALENDAR.day_of_week_start)
      : 0,
    calendrData = RNB_CALENDAR.calendar_data ? RNB_CALENDAR.calendar_data : '';

  for (let key in calendrData) {
    events.push(calendrData[key]);
  }

  let calendarEl = document.getElementById('redq-rental-calendar');

  function handleDatesRender(arg) {
    console.log('viewType:', arg.view.calendar.state.viewType);
  }
  let calendar = new FullCalendar.Calendar(calendarEl, {
    plugins: ['dayGrid', 'timeGrid', 'list'],
    defaultView: 'dayGridMonth',
    datesRender: handleDatesRender,
    header: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
    },
    buttonText: {
      today: RNB_CALENDAR.label.today,
      month: RNB_CALENDAR.label.month,
      week : RNB_CALENDAR.label.week,
      day  : RNB_CALENDAR.label.day,
      list : RNB_CALENDAR.label.list
    },
    locale: initialLocaleCode,
    firstDay: dayOfWeekStart,
    // displayEventTime: false,
    eventRender: function (info) {},
    events: events,
    eventClick: function (info) {
      info.jsEvent.preventDefault();
      $('#eventProduct').html(info.event.title);
      $('#eventProduct').attr('href', info.event.extendedProps.link);
      $('#eventInfo').html(info.event.extendedProps.description);
      $('#eventLink').attr('href', info.event.url);
      $.magnificPopup.open({
        items: {
          src: '#eventContent',
          type: 'inline',
        },
      });
    },
  });
  calendar.render();
  console.log(RNB_CALENDAR);
})(jQuery);
