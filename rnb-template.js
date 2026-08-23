var RNB_TEMPLATES = {};
jQuery(document).ready(function ($) {
  initChosen = () => {
    $('.redq-select-boxes').chosen();
  };

  /**
   * pickupLocation
   *
   * @param location
   */
  pickupLocation = (location) => {
    if (!$('#pickupLocationBuilder').length) {
      return;
    }

    let selectedPickupLocation = RNB_URL_DATA.url_data.pickup_location
      ? RNB_URL_DATA.url_data.pickup_location
      : null;

    const builderTemplate = _.template($('#pickupLocationBuilder').html())({
      items: location.data,
      selectedItem: selectedPickupLocation,
      title: location.title,
      placeholder: location.placeholder,
    });
    $('#pickupLocationPreview').html(builderTemplate);
    $('.redq-select-boxes').chosen();
  };

  /**
   * dropoffLocation
   *
   * @param location
   */
  dropoffLocation = (location) => {
    if (!$('#dropoffLocationBuilder').length) {
      return;
    }

    let selectedReturnLocation = RNB_URL_DATA.url_data.return_location
      ? RNB_URL_DATA.url_data.return_location
      : null;

    const builderTemplate = _.template($('#dropoffLocationBuilder').html())({
      items: location.data,
      selectedItem: selectedReturnLocation,
      title: location.title,
      placeholder: location.placeholder,
    });
    $('#dropoffLocationPreview').html(builderTemplate);

    $('.redq-select-boxes').chosen();
  };

  /**
   * Resource
   *
   * @param resource
   * @param resourceContainerClass
   */
  resource = (resource) => {
    if (!$('#resourceBuilder').length) {
      return;
    }

    let selectedResources = RNB_URL_DATA.url_data.resources
      ? RNB_URL_DATA.url_data.resources
      : [];

      console.log(selectedResources, 'selectedResources');

    const builderTemplate = _.template($('#resourceBuilder').html())({
      items: resource.data,
      selectedItems: selectedResources,
      title: resource.title,
    });
    $('#resourcePreview').html(builderTemplate);
  };

  /**
   * resourceModal
   *
   * @param resource
   */
  resourceModal = (resource) => {
    if (!$('#resourceModalBuilder').length || !resource.data.length) {
      return;
    }

    const builderTemplate = _.template($('#resourceModalBuilder').html())({
      items: resource.data,
      title: resource.title,
    });
    $('#resourceModalPreview').html(builderTemplate);
  };

  /**
   * category
   *
   * @param category
   * @param containerClass
   */
  category = (category) => {
    if (!$('#categoryBuilder').length) {
      return;
    }

    let selectedCategories = RNB_URL_DATA.url_data.categories
      ? RNB_URL_DATA.url_data.categories
      : [];

    const builderTemplate = _.template($('#categoryBuilder').html())({
      items: category.data,
      selectedItems: selectedCategories,
      title: category.title,
    });
    $('#categoryPreview').html(builderTemplate);
  };

  /**
   * adults
   *
   * @param person
   */
  adults = (adult) => {
    if (!$('#adultBuilder').length) {
      return;
    }

    let selectedPerson = RNB_URL_DATA.url_data.person
      ? RNB_URL_DATA.url_data.person
      : null;

    const builderTemplate = _.template($('#adultBuilder').html())({
      items: adult.data,
      selectedItem: selectedPerson,
      title: adult.title,
      placeholder: adult.placeholder,
    });
    $('#adultPreview').html(builderTemplate);
    $('.redq-select-boxes').chosen();
  };

  /**
   * adultModal
   *
   * @param adult
   */
  adultModal = (adult) => {
    if (!$('#adultModalBuilder').length || !adult.data.length) {
      return;
    }

    const builderTemplate = _.template($('#adultModalBuilder').html())({
      items: adult.data,
      title: adult.title,
      placeholder: adult.placeholder,
    });
    $('#adultModalPreview').html(builderTemplate);
  };

  /**
   * childs
   *
   * @param person
   */
  childs = (child) => {
    if (!$('#childBuilder').length) {
      return;
    }

    let selectedPerson = RNB_URL_DATA.url_data.person
      ? RNB_URL_DATA.url_data.person
      : null;

    const builderTemplate = _.template($('#childBuilder').html())({
      items: child.data,
      selectedItem: selectedPerson,
      title: child.title,
      placeholder: child.placeholder,
    });
    $('#childPreview').html(builderTemplate);
    $('.redq-select-boxes').chosen();
  };

  /**
   * childModal
   *
   * @param child
   */
  childModal = (child) => {
    if (!$('#childModalBuilder').length || !child.data.length) {
      return;
    }
    const builderTemplate = _.template($('#childModalBuilder').html())({
      items: child.data,
      title: child.title,
      placeholder: child.placeholder,
    });
    $('#childModalPreview').html(builderTemplate);
  };

  /**
   * displayDeposit
   *
   * @param deposit
   */
  deposit = (deposit) => {
    if (!$('#depositBuilder').length) {
      return;
    }
    const selectedDeposits = getUrlParameter('tex_deposite');
    const builderTemplate = _.template($('#depositBuilder').html())({
      items: deposit.data,
      selectedItems: selectedDeposits ? selectedDeposits.split(',') : [],
      title: deposit.title,
    });
    $('#depositPreview').html(builderTemplate);
  };

  /**
   * depositModal
   *
   * @param deposits
   */
  depositModal = (deposit) => {
    if (!$('#depositModalBuilder').length || !deposit.data.length) {
      return;
    }

    const builderTemplate = _.template($('#depositModalBuilder').html())({
      items: deposit.data,
      title: deposit.title,
    });
    $('#depositModalPreview').html(builderTemplate);
  };

  var getUrlParameter = function getUrlParameter(sParam) {
    var sPageURL = window.location.search.substring(1),
      sURLVariables = sPageURL.split('&'),
      sParameterName,
      i;

    for (i = 0; i < sURLVariables.length; i++) {
      sParameterName = sURLVariables[i].split('=');

      if (sParameterName[0] === sParam) {
        return sParameterName[1] === undefined
          ? true
          : decodeURIComponent(sParameterName[1]);
      }
    }
  };

  RNB_TEMPLATES = {
    initChosen,
    pickupLocation,
    dropoffLocation,
    resource,
    resourceModal,
    category,
    adults,
    childs,
    adultModal,
    childModal,
    deposit,
    depositModal,
  };
});
