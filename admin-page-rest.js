(function ($) {
  'use_strict';

  let initialLocaleCode = RNB_CALENDAR.lang_domain
      ? RNB_CALENDAR.lang_domain
      : 'en',
    dayOfWeekStart = RNB_CALENDAR.day_of_week_start
      ? RNB_CALENDAR.day_of_week_start
      : 0;

  let calendarEl = document.getElementById('redq-rental-calendar');
  let calendar = new FullCalendar.Calendar(calendarEl, {
    plugins: ['dayGrid', 'timeGrid', 'list'],
    defaultView: 'dayGridMonth',
    locale: 'de', // initialLocaleCode,
    firstDay: dayOfWeekStart,
    events: function (info, successCallback, failureCallback) {
      let start = info.startStr;
      let end = info.endStr;
      $.ajax({
        url: '/wp-json/rnb/v1/events',
        method: 'POST',
        data: {
          start: start,
          end: end,
        },
        beforeSend: function (xhr) {
          $('#loader').removeClass('hidden');
        },
        success: function (response) {
          let events = response;
          successCallback(events);
        },
        error: function () {
          failureCallback('There was an error fetching events.');
        },
        complete: function () {
          $('#loader').addClass('hidden');
        },
      });
    },
    eventDidMount: function (info) {
      info.el.style.backgroundColor = '#f00';
      info.el.style.borderColor = '#f00';
    },
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
})(jQuery);
