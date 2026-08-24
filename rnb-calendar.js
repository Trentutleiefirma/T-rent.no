var RNB_CALENDER_ACTION = {};
jQuery(document).ready(function ($) {
  const weekDaysAra = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
  const dateTimeOptions = {};
  const dropDateTimeOptions = {};

  timeConvert = (time) => {
    if (time === '24:00') {
      return '11:59 pm';
    }
    time = time
      .toString()
      .match(/^([01]\d|2[0-3])(:)([0-5]\d)(:[0-5]\d)?$/) || [time];
    if (time.length > 1) {
      time = time.slice(1);
      time[5] = +time[0] < 12 ? ' am' : ' pm';
      time[0] = +time[0] % 12 || 12;
    }
    return time.join('');
  };

  getBlockDates = (CALENDAR_DATA) => {
    let dates = [];
    if (CALENDAR_DATA.buffer_days) {
      const allDates = CALENDAR_DATA.block_dates.concat(
        CALENDAR_DATA.buffer_days
      );
      dates = allDates.filter((v, i, a) => a.indexOf(v) === i);
    }
    return dates;
  };

  rnb_handle_time_restriction = (
    conditional_data,
    validation_data,
    currentDateTime,
    calendarDate
  ) => {
    let euroFormat = conditional_data.euro_format;
    let selectedDay;
    let selectedDate;
    let selectedMonth;
    let currentMonth = currentDateTime.getMonth();

    if (euroFormat === 'yes') {
      const splitDate = calendarDate.split('/');
      const finalDate = `${splitDate[1]}/${splitDate[0]}/${splitDate[2]}`;
      selectedDay = new Date(finalDate).getDay();
      selectedDate = new Date(finalDate).getDate();
      selectedMonth = new Date(finalDate).getMonth();
    } else {
      const dateObj = new Date(calendarDate);
      selectedDay = dateObj.getDay();
      selectedDate = dateObj.getDate();
      selectedMonth = dateObj.getMonth();
    }

    const getToday = currentDateTime.getDay();
    const todayMinTime =
      conditional_data.time_format === '24-hours'
        ? currentDateTime.toLocaleString('en-US', {
            hour: 'numeric',
            minute: 'numeric',
            hour12: false,
          })
        : currentDateTime.toLocaleString('en-US', {
            hour: 'numeric',
            minute: 'numeric',
            hour12: true,
          });

    if (currentDateTime.getDate() === selectedDate && currentMonth === selectedMonth) {
      const opening_closing_copy = JSON.parse(
        JSON.stringify(validation_data.openning_closing)
      );
      opening_closing_copy[weekDaysAra[getToday]].min = todayMinTime;
      return [selectedDay, opening_closing_copy];
    } else {
      return [selectedDay, validation_data.openning_closing];
    }
  };

  mobileCalendarCloseBtn = (args) => {
    $(args.closeButtonId).on('click', function () {
      $(args.modalBodyId).hide();
      $(args.dateId).datetimepicker('destroy');
    });
  };

  rnbHandleMobileSubmitEvent = (
    params,
    dateOptions,
    dateTimeOptions,
    timeOptions
  ) => {
    $(params.elementId).on('click', function () {
      $(params.modalBodyId).hide();
      $(params.dateId).datetimepicker('destroy');
      $(params.dateId).datetimepicker(
        Object.assign(dateOptions, {
          value: dateTimeOptions['date'],
        })
      );
      $(params.timeId).datetimepicker(timeOptions);
      $('form.cart').trigger('change');
      $(params.dateId).datetimepicker('destroy');
    });
  };

  function calendarInit(CALENDAR_DATA) {
    const conditional_data = CALENDAR_DATA.calendar_props.settings.conditions;
    const general_data = CALENDAR_DATA.calendar_props.settings.general;
    const validation_data = CALENDAR_DATA.calendar_props.settings.validations;

    let opening_closing = validation_data.openning_closing;
    const opening_closing_copy = clone(opening_closing);

    const getTRentOpeningTime = function (selectedDay) {
      return selectedDay === 0 || selectedDay === 6 ? '09:00' : '08:00';
    };

    const formatTRentTime = function (time) {
      return conditional_data.time_format === '24-hours'
        ? time
        : timeConvert(time);
    };

    const OpeningClosingTimeLogic = function (currentDateTime) {
      const pickupDate = $('#pickup-date').val();
      const results = rnb_handle_time_restriction(
        conditional_data,
        validation_data,
        currentDateTime,
        pickupDate
      );

      const selectedDay = results[0];
      const openingTime = getTRentOpeningTime(selectedDay);

      this.setOptions({
        minTime: formatTRentTime(openingTime),
        maxTime: formatTRentTime('21:00'),
        format: conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
        formatTime:
          conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
      });
    };

    const DropOffOpeningClosingTimeLogic = function (currentDateTime) {
      let minsToAdd;
      let time;
      const dropoffDate = $('#dropoff-date').val()
        ? $('#dropoff-date').val()
        : $('#pickup-date').val();
      const results = rnb_handle_time_restriction(
        conditional_data,
        validation_data,
        currentDateTime,
        dropoffDate
      );
      const selectedDay = results[0];
      let minTime = getTRentOpeningTime(selectedDay);

      if ($('#dropoff-date').length > 0) {
        if ($('#pickup-date').val() === $('#dropoff-date').val()) {
          if (
            $('#pickup-time').val() !== '' &&
            $('#pickup-time').val() !== '00:00'
          ) {
            time = $('#pickup-time').val();
            minsToAdd = parseInt(conditional_data.time_interval || 30);

            minTime = new Date(
              new Date('1970/01/01 ' + time).getTime() + minsToAdd * 60000
            )
              .toLocaleTimeString('en-US', {
                hour: 'numeric',
                hour12:
                  conditional_data.time_format === '24-hours' ? false : true,
                minute: 'numeric',
              })
              .replace('AM', 'am')
              .replace('PM', 'pm');
          }
        }
      } else if (
        $('#pickup-time').val() !== '' &&
        $('#pickup-time').val() !== '00:00'
      ) {
        time = $('#pickup-time').val();
        minsToAdd = parseInt(conditional_data.time_interval || 30);

        minTime = new Date(
          new Date('1970/01/01 ' + time).getTime() + minsToAdd * 60000
        )
          .toLocaleTimeString('en-US', {
            hour: 'numeric',
            hour12:
              conditional_data.time_format === '24-hours' ? false : true,
            minute: 'numeric',
          })
          .replace('AM', 'am')
          .replace('PM', 'pm');
      }

      this.setOptions({
        minTime:
          typeof minTime === 'string' && minTime.indexOf(':') !== -1
            ? minTime
            : formatTRentTime(getTRentOpeningTime(selectedDay)),
        maxTime: formatTRentTime('20:00'),
        format: conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
        formatTime:
          conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
      });
    };

    const onShow = function (ct) {
      $('#dropoff-date').val('');
      this.setOptions({
        minDate: 0,
        disabledDates: final,
      });
    };

    const onSelectDate = function (ct, $i) {
      const allowedTimes = CALENDAR_DATA.allowed_datetime;
      dateTimeOptions['date'] = ct;
      dropDateTimeOptions['date'] = ct;
      if (
        allowedTimes[ct.dateFormat(conditional_data.date_format)] !== undefined
      ) {
        if (
          allowedTimes[ct.dateFormat(conditional_data.date_format)].length === 0
        ) {
          ['#pickup-time', '#dropoff-time'].forEach((elementId) => {
            $(elementId).datetimepicker({
              datepicker: false,
              timepicker: false,
            });
          });
        } else {
          ['#pickup-time', '#dropoff-time'].forEach((elementId) => {
            $(elementId).datetimepicker({
              datepicker: false,
              format:
                conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
              formatTime:
                conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
              allowTimes:
                allowedTimes[ct.dateFormat(conditional_data.date_format)],
              onShow:
                elementId === '#pickup-time'
                  ? OpeningClosingTimeLogic
                  : DropOffOpeningClosingTimeLogic,
            });
          });
        }
      } else {
        ['#pickup-time', '#dropoff-time'].forEach((elementId) => {
          $(elementId).datetimepicker('destroy');
          $(elementId).datetimepicker({
            datepicker: false,
            format:
              conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
            formatTime:
              conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
            step: conditional_data.time_interval
              ? parseInt(conditional_data.time_interval)
              : 5,
            scrollInput: false,
            onShow:
              elementId === '#pickup-time'
                ? OpeningClosingTimeLogic
                : DropOffOpeningClosingTimeLogic,
            allowTimes: conditional_data.allowed_times,
          });
        });
      }
    };

    let offDays = [];
    if (conditional_data.weekends !== undefined) {
      const offDaysLength = conditional_data.weekends.length;
      for (let i = 0; i < offDaysLength; i++) {
        offDays.push(parseInt(conditional_data.weekends[i]));
      }
    }

    let domain =
      general_data.lang_domain !== false ? general_data.lang_domain : 'en';
    $.datetimepicker.setLocale(domain);

    $('#pickup-date').change(function (e) {
      $('#pickup-time').val('');
      $('#dropoff-time').val('');
    });

    $('#dropoff-date').change(function (e) {
      $('#dropoff-time').val('');
    });

    const final = getBlockDates(CALENDAR_DATA);

    const calendarOptions = {
      timepicker: false,
      scrollMonth: false,
      dayOfWeekStart: general_data.day_of_week_start
        ? general_data.day_of_week_start
        : 0,
      format: conditional_data.date_format,
      minDate: 0,
      disabledDates: final,
      formatDate: conditional_data.date_format,
      disabledWeekDays: offDays,
      scrollInput: false,
    };

    if (RNB_URL_DATA?.block_future_date) {
      let numberOfDays = parseInt(RNB_URL_DATA?.block_future_date);
      let nextDate = new Date(
        new Date().setDate(new Date().getDate() + numberOfDays)
      );
      calendarOptions.maxDate = nextDate;
    }

    const datepickerOption = Object.assign({}, calendarOptions, {
      onShow: onShow,
      onSelectDate: onSelectDate,
    });
    const mobilePickupDatePickerOptions = Object.assign({}, datepickerOption, {
      inline: true,
      onSelectTime: function (ct) {
        dateTimeOptions['time'] = ct;
      },
    });

    const dropDatepickerOption = Object.assign({}, calendarOptions, {
      onShow: function (ct) {
        this.setOptions({
          minDate: $('#pickup-date').val() ? $('#pickup-date').val() : 0,
          disabledDates: final,
        });
      },
      onSelectDate: onSelectDate,
    });
    const mobileDropoffDatePickerOptions = Object.assign(
      {},
      dropDatepickerOption,
      {
        inline: true,
        disabledWeekDays: offDays,
        onSelectTime: function (ct) {
          dropDateTimeOptions['time'] = ct;
        },
        scrollInput: true,
      }
    );

    if (window.innerWidth <= 480) {
      const mobileTimeOptions = {
        format: conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
        formatTime:
          conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
      };
      const mobilePickupTimeOptions = Object.assign({}, mobileTimeOptions, {
        value: dateTimeOptions['time'],
        onShow: OpeningClosingTimeLogic,
      });
      const mobileDropoffTimeOptions = Object.assign({}, mobileTimeOptions, {
        value: dropDateTimeOptions['time'],
        onShow: DropOffOpeningClosingTimeLogic,
      });
      const elementIds = {
        pickup: {
          elementId: '#cal-submit-btn',
          closeButtonId: '#cal-close-btn',
          modalBodyId: '#pickup-modal-body',
          dateId: '#pickup-date',
          timeId: '#pickup-time',
        },
        dropoff: {
          elementId: '#drop-cal-submit-btn',
          closeButtonId: '#drop-cal-close-btn',
          modalBodyId: '#dropoff-modal-body',
          dateId: '#dropoff-date',
          timeId: '#dropoff-time',
        },
      };

      $('#pickup-date').datetimepicker('destroy');

      $('#pickup-date').on('click', function () {
        $(elementIds.pickup.modalBodyId).show();
        $('#mobile-datepicker').datetimepicker(mobilePickupDatePickerOptions);
        mobileCalendarCloseBtn(elementIds.pickup);
        rnbHandleMobileSubmitEvent(
          elementIds.pickup,
          datepickerOption,
          dateTimeOptions,
          mobilePickupTimeOptions
        );
      });

      $('#dropoff-date').on('click', function () {
        $(elementIds.dropoff.modalBodyId).show();
        $('#drop-mobile-datepicker').datetimepicker(
          mobileDropoffDatePickerOptions
        );
        mobileCalendarCloseBtn(elementIds.dropoff);
        rnbHandleMobileSubmitEvent(
          elementIds.dropoff,
          dropDatepickerOption,
          dropDateTimeOptions,
          mobileDropoffTimeOptions
        );
      });

      if (RNB_URL_DATA.date) {
        const elementIds = ['#pickup-time', '#dropoff-time'];
        elementIds.forEach((elementId) => {
          $(elementId).datetimepicker('destroy');
          $(elementId).datetimepicker({
            datepicker: false,
            format:
              conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
            formatTime:
              conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
            step: conditional_data.time_interval
              ? parseInt(conditional_data.time_interval)
              : 5,
            scrollInput: false,
            onShow:
              elementId === '#pickup-time'
                ? OpeningClosingTimeLogic
                : DropOffOpeningClosingTimeLogic,
            allowTimes: conditional_data.allowed_times,
          });
        });
      }
    } else {
      $('#pickup-date').datetimepicker('destroy');
      $('#pickup-date').datetimepicker(datepickerOption);
      $('#dropoff-date').datetimepicker('destroy');
      $('#dropoff-date').datetimepicker(dropDatepickerOption);

      if (RNB_URL_DATA.date) {
        const elementIds = ['#pickup-time', '#dropoff-time'];
        elementIds.forEach((elementId) => {
          $(elementId).datetimepicker('destroy');
          $(elementId).datetimepicker({
            datepicker: false,
            format:
              conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
            formatTime:
              conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
            step: conditional_data.time_interval
              ? parseInt(conditional_data.time_interval)
              : 5,
            scrollInput: false,
            onShow:
              elementId === '#pickup-time'
                ? OpeningClosingTimeLogic
                : DropOffOpeningClosingTimeLogic,
            allowTimes: conditional_data.allowed_times,
          });
        });
      }
    }
  }

  RNB_CALENDER_ACTION = {
    init: calendarInit,
  };
});
