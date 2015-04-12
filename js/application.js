/**
 * Created by root on 13/4/15.
 */
var bygApp = angular.module("bygApp", ["ngRoute", "angularUUID2"])
    .constant("baseUrl", "http://localhost")
    .config(function ($routeProvider) {
        $routeProvider.when("/contact-us", {
            templateUrl: "views/contact-us.html"
        });

        $routeProvider.when("/terms-condition", {
            templateUrl: "views/terms-condition.html"
        });
        $routeProvider.when("/privacy-policy", {
            templateUrl: "views/privacy-policy.html"
        });
        $routeProvider.when("/faq", {
            templateUrl: "views/faq.html"
        });
        $routeProvider.when("/how-it-works", {
            templateUrl: "views/how-it-works.html"
        });

        $routeProvider.when("/ground-details", {
            templateUrl: "views/ground-details.html"
        });
        $routeProvider.when("/view-slots", {
            templateUrl: "views/view-slots.html"
        });

        $routeProvider.when("/checkout-summary", {
            templateUrl: "views/checkout-summary.html"
        });
        $routeProvider.when("/support-details", {
            templateUrl: "views/support-details.html"
        });
        $routeProvider.when("/thank-you", {
            templateUrl: "views/thank-you.html"
        });


        $routeProvider.otherwise({
            templateUrl: "views/home.html"
        });
    });

bygApp.service('initHome', function ($http,baseUrl) {
    this.getCityAndLocation = function () {
        return $http.get(baseUrl+"/sports-grounds").success(function (data) {
            console.log(data);
            return data;
        });
        this.getGroundDetails = function ($date, $sportsName, $location) {
            return $http.get(
                {
                    url: baseUrl+"/sports-grounds/search",
                    method: "GET",
                    params: {sportsName: $sportsName, date: $date, location: $location}
                }
            ).success(function (data) {
                    console.log(data);
                    return data;
                });
        };
    };
});

bygApp.directive("datepicker", function () {
    return {
        restrict: "A",
        require: "ngModel",
        link: function (scope, elem, attrs, controller) {
            var updateModel = function (dateText) {
                scope.$apply(function () {
                    controller.$setViewValue(dateText);
                });
            };
            var dateToday = new Date();
            var options = {
                dateFormat: "yy-mm-dd",
                duration: "fast",
                defaultDate: "-1w",
                changeMonth: false,
                numberOfMonths: 1,
                minDate: dateToday,
                onSelect: function (dateText) {
                    updateModel(dateText);
                }

            };
            $(elem).datepicker(options);
        }
    }
});

bygApp.controller("bygMainCntlr", function ($scope, $http, initHome, $filter,uuid2,baseUrl) {

        $scope.dateOptions = {
            changeYear: false,
            changeMonth: true,
            currentText: "Now",
            yearRange: '1900:-0',
            dateFormat: "yy-MM-dd",
            defaultDate: "11m"
        };
        var baseUrl=baseUrl;
        $scope.bookingStartTime;
        $scope.userSessionId = uuid2.newguid();
        $scope.searchRequest = {};
        $scope.groundDetails;
        $scope.selectedGround = {};
        $scope.createBookingRequest = { sportsGroundId:"",bookingType:"ONLINE",webIp:"",webSessionId:"", date:"",slot:"" }
        $scope.slots = {sportsGroundId:"",slots:[]};
        $scope.bookingSummary = {baseAmt:0,bookingAmt:0,serviceTax:0,onlineFees:15,totalAmount:""};
        $scope.userDetails ={userId:"",name:"",email:"",phoneNumber:""};
        $scope.userBookingId ="";
        $scope.userId ="";
        $scope.updateSlotsToBookingRequest = { bookingId:"",date:"",slot:""};
        $scope.searchPanel = {location:[], sports:[],selectedLocation:"Bangalore",selectedSports:"",selectedDate:""};
        $scope.numberOfSlotSelected = 0;
        $scope.numberOfBookedHours = 0;
        console.log($scope.userSessionId);

        initHome.getCityAndLocation().then(function (result) {
            $scope.sports = result.data;

            function uniqueCollection(collection,keyName) {
                var output = [],
                    keys = [];

                angular.forEach(collection, function(item) {
                    var key = item[keyName];
                    if(keys.indexOf(key) === -1) {
                        keys.push(key);
                        output.push(item[keyName]);
                    }
                });
                return output;
            }

            $scope.searchPanel.sports = uniqueCollection(result.data,"Sport");
            $scope.searchPanel.locations = uniqueCollection(result.data,"City");
            //$scope.searchPanel.selectedLocation = "Bangaluru";
            //$scope.searchPanel.selectedSports = "Football";


        });

        //data access functions starts here

        $scope.viewGroundDetails = function (initHome) {
            $scope.resetSession();
            var date = $filter('date')($scope.searchRequest.date, 'yyyy-MM-dd');
            var location = $scope.searchRequest.location;
            var sportsName = $scope.searchRequest.sportsName;
            console.log(date)
            console.log(location)
            console.log(sportsName)
            $http(
                {
                    url: baseUrl+"/sports-grounds/search",
                    method: "GET",
                    params: {sportsName: sportsName, date: date, location: location}
                }
            ).success(function (data) {
                    console.log(data);
                    return data;
                }).then(function (result) {
                    $scope.groundDetails = result.data;
                    angular.forEach($scope.groundDetails, function (value) {
                        value.image = "img/".concat(value.SportsGroundID).concat(".jpeg");
                    });
                });

            console.log($scope.groundDetails)


        };
        $scope.cancelAssociatedSlots = function() {
            angular.forEach($scope.slots.slots, function (slot) {
                if (slot.selected == true) {

                    $http(
                        {
                            url: baseUrl + "/bookings/" + $scope.userBookingId + "/slots/" + slot.slot,
                            method: "PUT",
                            data: {date: $scope.searchRequest.date},
                            headers: {
                                'Content-Type': 'application/json; charset=utf-8'
                            }

                        }
                    ).success(function (data) {
                            console.log(data);
                            return data;
                        }
                    ).then(function (result) {
                            console.log("calcelled slot  against bokking -->".concat($scope.userBookingId).concat(" and slot ").concat(slot.slot).concat("--->").concat(result.data));
                        }
                    );
                }
            });
        }

        $scope.viewSlots = function($selectedGround) {

            if((!angular.isUndefined($scope.selectedGround.SportsGroundID))){
                console.log(" need reset");
                $scope.cancelAssociatedSlots();

            }

            $scope.selectedGround = $selectedGround;
            $scope.slots.sportsGroundId =$selectedGround.SportsGroundID;
            var date =  $scope.searchRequest.date;//$filter('date')('2015-01-04', 'yyyy-MM-dd');//$filter('date')($scope.searchRequest.date, 'yyyy-MM-dd');
            //$scope.searchRequest.date = date;

            return $http(
                {
                    url: baseUrl+"/sports-grounds/" + $selectedGround.SportsGroundID,
                    method: "GET",
                    params: {date: date}
                }
            ).success(function (data) {
                    console.log(data);
                    return data;
                }).then(function (result) {
                    // populate array map for rates
                    var rates  = result.data.allRates;

                    var ratesBySlot = new Array(rates.length);
                    angular.forEach(rates, function (rate) {
                        ratesBySlot[rate.Slot]=rate.Rate;
                    })

                    var retrievedSlots  = result.data.bookedSlots;
                    var bookedSlotsBySlot = new Array(retrievedSlots.length);
                    angular.forEach(retrievedSlots, function (bookedSlot) {
                        bookedSlotsBySlot[bookedSlot.Slot]=bookedSlot;
                    })



                    var firstSlot = Number($selectedGround.dailyScheduleFirstSlot);
                    var lastSlot =Number($selectedGround.dailyScheduleLastSlot);


                    function isBooked($slotNumber) {
                        var isBooked = false;
                        angular.forEach(result.data, function (bookedSlot) {
                            if (bookedSlot.Slot === $slotNumber) {
                                isBooked = true;
                            }
                        })
                        return isBooked;
                    }

                    function getSportsGroundId() {
                        var sportsGroundId;
                        angular.forEach(result.data.slots, function (bookedSlot) {
                            sportsGroundId =  bookedSlot.SportsGroundID;
                        })

                        return sportsGroundId;


                    }

                    var slot = {startTime:"",endTime:"",rate:"",displayText:"",status:"",selected:false};


                    $scope.slots.slots= [];
                    if(result.data.length!=0){
                        var defaultDate = new Date(2015, 1, 1, 0, 0, 0, 0);
                        defaultDate.setHours(defaultDate.getHours()+Number(firstSlot));

                        for(var i=firstSlot;lastSlot!==defaultDate.getHours();i++) {
                            var vBookedSlot= bookedSlotsBySlot[String(i)];
                            var intermediateSlot = angular.copy(slot);
                            intermediateSlot.startTime = defaultDate.getHours()+":"+ (defaultDate.getMinutes()==0?"00":defaultDate.getMinutes()) ;

                            if($selectedGround.SlotType==='HalfHourly'){
                                defaultDate.setMinutes(defaultDate.getMinutes()+30);
                            }else{
                                defaultDate.setHours(defaultDate.getHours()+1);
                            }
                            intermediateSlot.endTime   = defaultDate.getHours()+":"+ (defaultDate.getMinutes()==0?"00":defaultDate.getMinutes()) ;
                            intermediateSlot.rate = ratesBySlot[String(i)];
                            intermediateSlot.displayText = "Rs.".concat(ratesBySlot[String(i)]);
                            intermediateSlot.status = (angular.isUndefined(vBookedSlot) && !angular.isUndefined(ratesBySlot[String(i)])) ? "Available":"Booked";
                            intermediateSlot.slot = String(i);
                            intermediateSlot.selected=false;
                            $scope.slots.slots.push(intermediateSlot);
                        }

                    }
                    console.log($scope.slots)
                });
            ;

        };
        $scope.checkIfSlotBelongsToGround= function($slotGroundId,$sportsGroundId){
            if($slotGroundId === $sportsGroundId){
                return false;
            }
            else{
                return true;
            }


        }
        $scope.buttonStyle = function (slot) {
            var slotButtonClass ="btn btn-primary btn-block";
            //  var slot = $scope.currentSelectedSlot ===undefined ? $slot: $scope.currentSelectedSlot;
            if ((slot.status === "Available") && (slot.selected === true)) {
                slotButtonClass = "btn btn-success btn-block";
            } else if (slot.status === "Available") {
                slotButtonClass = "btn btn-info btn-block";
            } else if (slot.status !== "Available") {
                slotButtonClass = "btn btn-default  btn-block disabled";
                slot.displayText = slot.status;

            }

            return slotButtonClass;
        }
        $scope.confirmBooking = function(){

            var bookingConfirmationRequest  = {bookingType:"ONLINE",userName:$scope.userDetails.name,userEmail:$scope.userDetails.email,phoneNumber:$scope.userDetails.phoneNumber};


            $http(
                {
                    url: baseUrl+"/bookings/"+$scope.userBookingId,
                    method: "PUT",
                    data: bookingConfirmationRequest,
                    headers: {
                        'Content-Type': 'application/json; charset=utf-8'
                    }
                }
            ).success(function (data) {
                    console.log(data);
                    return data;
                }
            ).then(function (result) {
                    $scope.userId = result.data.success;
                    console.log( $scope.userId);
                }
            );
            console.log("confirm booking");
            $scope.userBookingId="";
            //create user based on user information and confirm booking

        }
        $scope.slotSelectionAction = function (slot) {

            if ((slot.status === "Available")) {

                if ((slot.selected === false)) {
                    slot.displayText = "Selected";
                    slot.selected = true;
                    //create booking or add slot ot it
                    if($scope.userBookingId==="") {
                        var bookingRequest = angular.copy($scope.createBookingRequest);
                        bookingRequest.sportsGroundId = $scope.selectedGround.SportsGroundID;
                        bookingRequest.bookingType = "ONLINE";
                        bookingRequest.webIp = "0.0.0.0";
                        bookingRequest.webSessionId = $scope.userSessionId;
                        bookingRequest.date = $scope.searchRequest.date;
                        bookingRequest.slot = slot.slot;
                        $http(
                            {
                                url: baseUrl+"/sports-grounds/"+$scope.selectedGround.SportsGroundID+"/slots/"+slot.slot,
                                method: "POST",
                                data: bookingRequest,
                                headers: {
                                    'Content-Type': 'application/json; charset=utf-8'
                                }
                            }
                        ).success(function (data) {
                                console.log(data);
                                return data;
                            }
                        ).then(function (result) {
                                $scope.userBookingId = Number(result.data.success);
                                console.log($scope.userBookingId);
                            }
                        );

                    }else{
                        //add slots to it
                        var addSlotRequest = angular.copy($scope.updateSlotsToBookingRequest);
                        addSlotRequest.bookingId = $scope.userBookingId;
                        addSlotRequest.slot = slot.slot;
                        addSlotRequest.date =  $scope.searchRequest.date;
                        $http(
                            {
                                url: baseUrl+"/bookings/"+addSlotRequest.bookingId+"/slots",
                                method: "PUT",
                                data: addSlotRequest,
                                headers: {
                                    'Content-Type': 'application/json; charset=utf-8'
                                }
                            }
                        ).success(function (data) {
                                console.log(data);
                                return data;
                            }
                        ).then(function (result) {
                                console.log("added slots to booking".concat($scope.userBookingId).concat("--->").concat(result.data));
                            }
                        );
                    }
                } else {
                    slot.displayText = "Rs.".concat(slot.rate);
                    slot.selected = false;
                    //remove slot if it is un-selected
                    //add slots to it
                    var removeSlotRequest = angular.copy($scope.updateSlotsToBookingRequest);
                    removeSlotRequest.bookingId = $scope.userBookingId;
                    removeSlotRequest.slot = slot.slot;
                    removeSlotRequest.date =  $scope.searchRequest.date;
                    $http(
                        {
                            url: baseUrl+"/bookings/"+removeSlotRequest.bookingId+"/slots/"+removeSlotRequest.slot,
                            method: "DELETE",
                            data: removeSlotRequest,
                            headers: {
                                'Content-Type': 'application/json; charset=utf-8'
                            }

                        }
                    ).success(function (data) {
                            console.log(data);
                            return data;
                        }
                    ).then(function (result) {
                            console.log("removed slots to booking".concat($scope.userBookingId).concat(" and slot ").concat(removeSlotRequest.slot).concat("--->").concat(result.data));
                        }
                    );
                }


                //calculate summary
                var baseAmount = 0;
                var numberOfSlotSelected =0;
                angular.forEach($scope.slots.slots , function(eachSlot){
                    if(eachSlot.selected===true) {
                        baseAmount = baseAmount + Number(eachSlot.rate);
                        numberOfSlotSelected = numberOfSlotSelected+1;
                    }
                });
                $scope.numberOfSlotSelected = numberOfSlotSelected;
                $scope.bookingSummary.baseAmt = baseAmount;
                $scope.bookingSummary.bookingAmt = baseAmount/2;
                $scope.bookingSummary.totalAmount = $scope.bookingSummary.bookingAmt + $scope.bookingSummary.onlineFees;
            }
        }

        $scope.selectionCount = function () {
            var count = 0;
            angular.forEach($scope.slots.slots, function (slot) {
                if (slot.selected == true) {
                    count++;
                }
            });
            return count;
        }

        $scope.checkoutSummary = function(){
            console.log($scope.searchRequest.date)

            $scope.bookingStartTime  = $scope.getFirstBookedSlot();

            if(!angular.isUndefined($scope.selectedGround)){
                if($scope.selectedGround.SlotType==='HalfHourly'){
                    $scope.numberOfBookedHours = $scope.numberOfSlotSelected/2;
                }else{
                    $scope.numberOfBookedHours = $scope.numberOfSlotSelected;
                }
            }




        }
        $scope.getFirstBookedSlot = function () {
            var startTime;
            angular.forEach($scope.slots.slots, function (slot) {
                if (slot.selected == true) {
                    startTime = slot.startTime;
                }
            });
            return startTime;
        }

        $scope.resetSession = function(){
            //remove all slot from booking
            $scope.cancelAssociatedSlots();
            $scope.groundDetails = [];
            $scope.slots = {sportsGroundId:"",slots:[]};
            $scope.bookingSummary = {baseAmt:0,bookingAmt:0,serviceTax:0,onlineFees:15,totalAmount:""};
            $scope.numberOfSlotSelected =0;
            $scope.numberOfBookedHours =0;

        }

    }
);

/*  bygApp.controller('DatepickerDemoCtrl', function ($scope) {
 $scope.today = function () {
 $scope.dt = new Date();
 };
 $scope.today();

 $scope.clear = function () {
 $scope.dt = null;
 };

 // Disable weekend selection
 $scope.disabled = function (date, mode) {
 return ( mode === 'day' && ( date.getDay() === 0 || date.getDay() === 6 ) );
 };

 $scope.toggleMin = function () {
 $scope.minDate = $scope.minDate ? null : new Date();
 };
 $scope.toggleMin();

 $scope.open = function ($event) {
 $event.preventDefault();
 $event.stopPropagation();

 $scope.opened = true;
 };

 $scope.dateOptions = {
 formatYear: 'yy',
 startingDay: 1
 };

 $scope.formats = ['dd-MMMM-yyyy', 'yyyy/MM/dd', 'dd.MM.yyyy', 'shortDate'];
 $scope.format = $scope.formats[0];
 });
 */

