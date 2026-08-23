// Rnb Magic Google Map Code starts here
function geocodePosition(pos, ID) {
  geocoder = new google.maps.Geocoder();
  geocoder.geocode(
    {
      latLng: pos,
    },
    function(responses) {
      if (responses && responses.length > 0) {
        // updateMarkerAddress();
        jQuery(ID).val(responses[0].formatted_address);
      } else {
        // updateMarkerAddress("Cannot determine address at this location.");
      }
    }
  );
}

window.onload = function(event) {
  var google = window.google;
  var origin = document.getElementById('rnb-origin-autocomplete');
  var destination = document.getElementById('rnb-destination-autocomplete');
  var markerOrigin = null;
  var markerDestination = null;
  var originValue,
    destinationValue = '';
  var map = new google.maps.Map(document.getElementById('rnb-map'), {
    zoom: 10,
    center: new google.maps.LatLng(37.774929, -122.419416),
  });
  var locationProtocol = location.protocol;
  if (locationProtocol === 'https:') {
    infoWindow = new google.maps.InfoWindow();
    // Try HTML5 geolocation.
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        function(position) {
          var pos = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
          };
          infoWindow.setPosition(pos);
          infoWindow.setContent('Your Current Location.');
          infoWindow.open(map);
          map.setCenter(pos);
        },
        function() {
          handleLocationError(true, infoWindow, map.getCenter());
        }
      );
    } else {
      // Browser doesn't support Geolocation
      handleLocationError(false, infoWindow, map.getCenter());
    }
  }

  function handleLocationError(browserHasGeolocation, infoWindow, pos) {
    infoWindow.setPosition(pos);
    infoWindow.setContent(
      browserHasGeolocation
        ? 'Error: The Geolocation service failed.'
        : "Error: Your browser doesn't support geolocation."
    );
    infoWindow.open(map);
  }

  var directionsService = new google.maps.DirectionsService();
  var directionsDisplay = new google.maps.DirectionsRenderer({
    map: map,
    //panel: document.getElementById("right-panel"),
    suppressMarkers: true,
  });
  var autocompleteOrigin = new google.maps.places.Autocomplete(origin);
  autocompleteOrigin.bindTo('bounds', map);
  google.maps.event.addDomListener(origin, 'keydown', function(event) {
    if (event.keyCode === 13) {
      event.preventDefault();
    }
  });
  google.maps.event.addDomListener(destination, 'keydown', function(event) {
    if (event.keyCode === 13) {
      event.preventDefault();
    }
  });
  var originLat = null;
  var originLang = null;
  autocompleteOrigin.addListener('place_changed', function() {
    var place = autocompleteOrigin.getPlace();
    originLat = place.geometry.location.lat();
    originLang = place.geometry.location.lng();
    if (originLat && originLang) {
      jQuery('#rnb-origin-autocomplete').attr('data-latlng', 'set');
    }
    if (!place.geometry) {
      window.alert("No details available for input: '" + place.name + "'");
      return;
    }
    jQuery('#rnb-destination-autocomplete').focus();
  });

  var autocompleteDestination = new google.maps.places.Autocomplete(
    destination
  );
  autocompleteDestination.bindTo('bounds', map);
  var destinationLat = null;
  var destinationLang = null;
  autocompleteDestination.addListener('place_changed', function() {
    var place = autocompleteDestination.getPlace();
    destinationLat = place.geometry.location.lat();
    destinationLang = place.geometry.location.lng();
    if (destinationLat && destinationLang) {
      jQuery('#rnb-destination-autocomplete').attr('data-latlng', 'set');
    }
    if (!place.geometry) {
      window.alert("No details available for input: '" + place.name + "'");
      return;
    }
    var originAddress = jQuery(origin).val();
    var destinationAddress = jQuery(destination).val();
    if (originAddress && destinationAddress) {
      displayRoute(
        originAddress,
        destinationAddress,
        directionsService,
        directionsDisplay,
        map
      );
    }
  });
  directionsDisplay.addListener('directions_changed', function() {
    computeTotalDistance(directionsDisplay.getDirections());
  });

  function displayRoute(origin, destination, service, display, map) {
    const self = this;
    var icons = {
      start: new google.maps.MarkerImage(
        // URL
        RNB_MAP.markers.pickup,
        // (width,height)
        new google.maps.Size(55, 40),
        // The origin point (x,y)
        new google.maps.Point(0, 0),
        // The anchor point (x,y)
        new google.maps.Point(0, 8)
      ),
      end: new google.maps.MarkerImage(
        // URL
        RNB_MAP.markers.destination,
        // (width,height)
        new google.maps.Size(55, 40),
        // The origin point (x,y)
        new google.maps.Point(0, 0),
        // The anchor point (x,y)
        new google.maps.Point(0, 8)
      ),
    };
    service.route(
      {
        origin: origin,
        destination: destination,
        travelMode: 'DRIVING',
        avoidTolls: true,
      },
      function(response, status) {
        if (status === 'OK') {
          display.setDirections(response);
          if (!self.prevMarkerOrigin && !self.prevMarkerDestination) {
            var leg = response.routes[0].legs[0];

            markerOrigin = makeMarker(
              leg.start_location,
              icons.start,
              RNB_MAP.pickup_title
            );
            markerDestination = makeMarker(
              leg.end_location,
              icons.end,
              RNB_MAP.dropoff_title
            );
            window.google.maps.event.addListener(
              markerOrigin,
              'dragend',
              function() {
                displayRoute(
                  markerOrigin.getPosition(),
                  markerDestination.getPosition(),
                  service,
                  display,
                  map
                );
                self.prevMarkerOrigin = markerOrigin;
                geocodePosition(
                  markerOrigin.getPosition(),
                  '#rnb-origin-autocomplete'
                );
              }
            );
            window.google.maps.event.addListener(
              markerDestination,
              'dragend',
              function() {
                displayRoute(
                  markerOrigin.getPosition(),
                  markerDestination.getPosition(),
                  service,
                  display,
                  map
                );
                geocodePosition(
                  markerDestination.getPosition(),
                  '#rnb-destination-autocomplete'
                );
                self.prevMarkerDestination = markerDestination;
              }
            );
          }
        } else {
          alert('Could not display directions due to: ' + status);
        }
      }
    );
  }
  function makeMarker(position, icon, title) {
    return new google.maps.Marker({
      position: position,
      map: map,
      icon: icon,
      title: title,
      draggable: true,
      animation: google.maps.Animation.DROP,
    });
  }
  function computeTotalDistance(result) {
    var total = 0,
      timeRequired = 0;
    var myroute = result.routes[0];
    for (var i = 0; i < myroute.legs.length; i++) {
      total += myroute.legs[i].distance.value;
      timeRequired += myroute.legs[i].duration.value;
    }
    total = total / 1000;
    total = total + '|' + timeRequired;
    jQuery('.rnb-distance').val(total);
    jQuery('form.cart').trigger('change');
  }
};
