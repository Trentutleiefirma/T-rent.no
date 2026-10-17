const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const php = fs.readFileSync(path.join(__dirname, '..', 'functions.php'), 'utf8');
const match = php.match(/\$trent_timepicker_fix = <<<'JS'\n([\s\S]*?)\nJS;/);

assert.ok(match, 'T-Rent time-picker script is present');

const script = match[1]
  .replace('__TRENT_SITE_TIMEZONE__', JSON.stringify('Europe/Oslo'))
  .replace('__TRENT_SITE_UTC_OFFSET__', '7200');

const fixedNow = Date.parse('2026-10-17T08:57:10Z');
class FakeDate extends Date {
  constructor(...args) {
    super(...(args.length ? args : [fixedNow]));
  }

  static now() {
    return fixedNow;
  }
}

const values = {
  '#pickup-date': '10/17/2026',
  '#pickup-time': '',
  '#dropoff-date': '10/17/2026',
  '#dropoff-time': '',
};
const pickerOptions = {};
const documentStub = {
  addEventListener() {},
};

function field(selector) {
  return {
    length: 1,
    val(value) {
      if (arguments.length) {
        values[selector] = value;
        return this;
      }
      return values[selector] || '';
    },
    datetimepicker(options) {
      pickerOptions[selector] = options;
      return this;
    },
    off() {
      return this;
    },
    on() {
      return this;
    },
  };
}

function jQuery(selector) {
  if (typeof selector === 'function') {
    selector();
    return undefined;
  }

  return field(selector === documentStub ? '__document__' : selector);
}

jQuery.fn = {
  datetimepicker() {},
};

const windowStub = {
  Intl,
  RNB_CALENDER_ACTION: {
    init() {},
  },
};

vm.runInNewContext(script, {
  window: windowStub,
  document: documentStub,
  jQuery,
  Date: FakeDate,
  Intl,
  Number,
  Object,
  Array,
  String,
  Math,
  parseInt,
  isNaN,
  setTimeout(callback) {
    callback();
  },
});

windowStub.RNB_CALENDER_ACTION.init({
  allowed_datetime: [],
  calendar_props: {
    settings: {
      conditions: {
        allowed_times: [],
        date_format: 'm/d/Y',
        euro_format: 'no',
        time_format: '24-hours',
        time_interval: '30',
      },
    },
  },
});

const pickupTimes = pickerOptions['#pickup-time'].allowTimes;

assert.equal(pickupTimes[0], '12:00');
assert.equal(pickupTimes.includes('10:00'), false);
assert.equal(pickupTimes.includes('11:30'), false);
assert.equal(pickupTimes.includes('12:00'), true);

console.log('PASS pickup slots use the shop timezone instead of the device timezone');
