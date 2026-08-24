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
      const allDates = CALENDAR_DATA.block_dates.concat(CALENDAR_DATA.buffer_days);
      dates = allDates.filter((v, i, a) => a.indexOf(v) === i);
    }
    return dates;
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

    const parseCalendarDate = function (calendarDate) {
      if (!calendarDate) {
        return null;
      }

      let dateObj;
      if (conditional_data.euro_format === 'yes') {
        const splitDate = calendarDate.split('/');
        if (splitDate.length !== 3) {
          return null;
        }
        dateObj = new Date(
          parseInt(splitDate[2], 10),
          parseInt(splitDate[1], 10) - 1,
          parseInt(splitDate[0], 10)
        );
      } else {
        const splitDate = calendarDate.split('/');
        if (splitDate.length === 3) {
          dateObj = new Date(
            parseInt(splitDate[2], 10),
            parseInt(splitDate[0], 10) - 1,
            parseInt(splitDate[1], 10)
          );
        } else {
          dateObj = new Date(calendarDate);
        }
      }

      return isNaN(dateObj.getTime()) ? null : dateObj;
    };

    const isSameLocalDate = function (dateA, dateB) {
      return (
        dateA &&
        dateB &&
        dateA.getFullYear() === dateB.getFullYear() &&
        dateA.getMonth() === dateB.getMonth() &&
        dateA.getDate() === dateB.getDate()
      );
    };

    const timeToMinutes = function (time) {
      if (!time) {
        return null;
      }

      const value = time.toString().trim().toLowerCase();
      const match24 = value.match(/^(\d{1,2}):(\d{2})$/);
      if (match24) {
        const hours = parseInt(match24[1], 10);
        const minutes = parseInt(match24[2], 10);
        if (hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59) {
          return hours * 60 + minutes;
        }
      }

      const match12 = value.match(/^(\d{1,2}):(\d{2})\s*(am|pm)$/);
      if (match12) {
        let hours = parseInt(match12[1], 10) % 12;
        const minutes = parseInt(match12[2], 10);
        if (match12[3] === 'pm') {
          hours += 12;
        }
        return hours * 60 + minutes;
      }

      return null;
    };

    const minutesTo24HourTime = function (minutes) {
      const normalized = Math.max(0, Math.min(1439, minutes));
      const hours = Math.floor(normalized / 60)
        .toString()
        .padStart(2, '0');
      const mins = (normalized % 60).toString().padStart(2, '0');
      return `${hours}:${mins}`;
    };

    const formatTRentTime = function (time) {
      return conditional_data.time_format === '24-hours'
        ? time
        : timeConvert(time);
    };

    const getTRentOpeningMinutes = function (selectedDay) {
      return selectedDay === 0 || selectedDay === 6 ? 9 * 60 : 8 * 60;
    };

    const getDateKey = function (dateObj) {
      if (!dateObj) {
        return '';
      }

      const dd = dateObj.getDate().toString().padStart(2, '0');
      const mm = (dateObj.getMonth() + 1).toString().padStart(2, '0');
      const yyyy = dateObj.getFullYear();

      if (conditional_data.euro_format === 'yes') {
        return `${dd}/${mm}/${yyyy}`;
      }
      return `${mm}/${dd}/${yyyy}`;
    };

    const getSourceTimes = function (selectedDate) {
      const allowedByDate = CALENDAR_DATA.allowed_datetime || {};
      const dateKey = getDateKey(selectedDate);

      if (dateKey && Object.prototype.hasOwnProperty.call(allowedByDate, dateKey)) {
        return Array.isArray(allowedByDate[dateKey]) ? allowedByDate[dateKey] : [];
      }

      return Array.isArray(conditional_data.allowed_times)
        ? conditional_data.allowed_times
        : null;
    };

    const getTRentTimeConfig = function (elementId, currentDateTime) {
      const isPickup = elementId === '#pickup-time';
      const calendarDate = isPickup
        ? $('#pickup-date').val()
        : ($('#dropoff-date').val() || $('#pickup-date').val());
      const selectedDate = parseCalendarDate(calendarDate);
      const selectedDay = selectedDate ? selectedDate.getDay() : currentDateTime.getDay();

      let minMinutes = getTRentOpeningMinutes(selectedDay);
      const maxMinutes = isPickup ? 21 * 60 : 20 * 60;

      if (selectedDate && isSameLocalDate(selectedDate, currentDateTime)) {
        const nowMinutes = currentDateTime.getHours() * 60 + currentDateTime.getMinutes();
        minMinutes = Math.max(minMinutes, nowMinutes);
      }

      if (!isPickup && $('#pickup-date').val() === $('#dropoff-date').val()) {
        const pickupMinutes = timeToMinutes($('#pickup-time').val());
        if (pickupMinutes !== null) {
          const interval = parseInt(conditional_data.time_interval || 30, 10);
          minMinutes = Math.max(minMinutes, pickupMinutes + interval);
        }
      }

      const sourceTimes = getSourceTimes(selectedDate);
      let filteredTimes = null;

      if (sourceTimes !== null) {
        filteredTimes = sourceTimes.filter(function (time) {
          const minutes = timeToMinutes(time);
          return minutes !== null && minutes >= minMinutes && minutes <= maxMinutes;
        });
      }

      return {
        minMinutes: minMinutes,
        maxMinutes: maxMinutes,
        filteredTimes: filteredTimes,
      };
    };

    const applyTRentTimeOptions = function (picker, elementId, currentDateTime) {
      const config = getTRentTimeConfig(elementId, currentDateTime || new Date());
      const options = {
        minTime: formatTRentTime(minutesTo24HourTime(config.minMinutes)),
        maxTime: formatTRentTime(minutesTo24HourTime(config.maxMinutes)),
        format: conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
        formatTime:
          conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
        step: conditional_data.time_interval
          ? parseInt(conditional_data.time_interval, 10)
          : 5,
        scrollInput: false,
      };

      if (config.filteredTimes !== null) {
        options.allowTimes = config.filteredTimes;
        options.timepicker = config.filteredTimes.length > 0;
      }

      picker.setOptions(options);
    };

    const OpeningClosingTimeLogic = function (currentDateTime) {
      applyTRentTimeOptions(this, '#pickup-time', currentDateTime);
    };

    const DropOffOpeningClosingTimeLogic = function (currentDateTime) {
      applyTRentTimeOptions(this, '#dropoff-time', currentDateTime);
    };

    const initTimePicker = function (elementId) {
      const onShow =
        elementId === '#pickup-time'
          ? OpeningClosingTimeLogic
          : DropOffOpeningClosingTimeLogic;

      $(elementId).datetimepicker('destroy');
      $(elementId).datetimepicker({
        datepicker: false,
        format: conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
        formatTime:
          conditional_data.time_format === '24-hours' ? 'H:i' : 'h:i a',
        step: conditional_data.time_interval
          ? parseInt(conditional_data.time_interval, 10)
          : 5,
        scrollInput: false,
        onShow: onShow,
      });
    };

    const onShow = function () {
      $('#dropoff-date').val('');
      this.setOptions({
        minDate: 0,
        disabledDates: final,
      });
    };

    const onSelectDate = function (ct) {
      dateTimeOptions['date'] = ct;
      dropDateTimeOptions['date'] = ct;
      initTimePicker('#pickup-time');
      initTimePicker('#dropoff-time');
    };

    let offDays = [];
    if (conditional_data.weekends !== undefined) {
      const offDaysLength = conditional_data.weekends.length;
      for (let i = 0; i < offDaysLength; i++) {
        offDays.push(parseInt(conditional_data.weekends[i], 10));
      }
    }

    let domain =
      general_data.lang_domain !== false ? general_data.lang_domain : 'en';
    $.datetimepicker.setLocale(domain);

    $('#pickup-date').change(function () {
      $('#pickup-time').val('');
      $('#dropoff-time').val('');
      initTimePicker('#pickup-time');
      initTimePicker('#dropoff-time');
    });

    $('#dropoff-date').change(function () {
      $('#dropoff-time').val('');
      initTimePicker('#dropoff-time');
    });

    $('#pickup-time').change(function () {
      $('#dropoff-time').val('');
      initTimePicker('#dropoff-time');
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
      const numberOfDays = parseInt(RNB_URL_DATA.block_future_date, 10);
      const nextDate = new Date(
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
      onShow: function () {
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
        step: conditional_data.time_interval
          ? parseInt(conditional_data.time_interval, 10)
          : 5,
        scrollInput: false,
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
        initTimePicker('#pickup-time');
        initTimePicker('#dropoff-time');
      }
    } else {
      $('#pickup-date').datetimepicker('destroy');
      $('#pickup-date').datetimepicker(datepickerOption);
      $('#dropoff-date').datetimepicker('destroy');
      $('#dropoff-date').datetimepicker(dropDatepickerOption);

      if (RNB_URL_DATA.date) {
        initTimePicker('#pickup-time');
        initTimePicker('#dropoff-time');
      }
    }
  }

  RNB_CALENDER_ACTION = {
    init: calendarInit,
  };
});