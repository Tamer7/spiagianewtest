<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Bigmapmapping;
use App\Models\SettingAdmin;
use App\Models\Place;
use App\Models\Booking;
use App\Models\TempBooking;
use App\Models\PromoCode;
use App\Models\TempbookingCard;
use App\Mail\SendMail;

use DateTime;
use Auth;

class PagesController extends Controller
{
    //

    public function error404(){
        return view('error404');
    }

    public function regulationsview(){
      $map_coods = Bigmapmapping::orderBy('id')->get();
      $maparray = array('map_coods' => $map_coods);
      return view('regulations')->with('maparray', $maparray);
    }
    public function pricesview(){
      $map_coods = Bigmapmapping::orderBy('id')->get();
      $maparray = array('map_coods' => $map_coods);
      return view('priceslist')->with('maparray', $maparray);
    }
    public function privacyview(){
      $map_coods = Bigmapmapping::orderBy('id')->get();
      $maparray = array('map_coods' => $map_coods);
      return view('privacy')->with('maparray', $maparray);
    }

    public function index(){
      // added for single page
      return redirect()->route('user.viewsmallplace', 'Sector Q&R');
      // eof for single page
      $map_coods = Bigmapmapping::orderBy('id')->get();
        $allplaces = Place::inRandomOrder()->get();

        $maps = Bigmapmapping::inRandomOrder()->limit('3')->get();

        $set_admin = SettingAdmin::orderBy('id')->first();

        $maparray = array('map_coods' => $map_coods, 'maps' => $maps, 'set_admin' => $set_admin);
        return view('users.homepage')->with('maparray', $maparray);
    }

    public function contact(){
      $map_coods = Bigmapmapping::orderBy('id')->get();
      $maparray = array('map_coods' => $map_coods);
      return view('users.contact')->with('maparray', $maparray);
    }

    public function viewsmallplace(Request $req){
      $map_name="Sectors";
      $set_admin = SettingAdmin::orderBy('id')->first();
      $map_coods = Bigmapmapping::orderBy('id')->get();
      $checkin_date = $req->t_start;
      $checkout_date = $req->t_end;

      function datediffcount($checkin, $checkout){
        $checkin = strtotime($checkin);
        $checkout = strtotime($checkout);
        $datediff = $checkout-$checkin;
          if(round($datediff / (60 * 60 * 24))<0){
            return 0;
          }
        return round($datediff / (60 * 60 * 24))+1;
    }

    $no_of_days = datediffcount($checkin_date, $checkout_date);


      if($checkin_date=="null")
        return redirect()->route('user.viewsmallplace', $map_name);
      $nmofdays = ($no_of_days)-1;
      // $nmofdays = ceil(abs(strtotime($checkout_date) - strtotime($checkin_date)) / 86400)+1;

      if(number_format($no_of_days)<=0)
        $nmofdays = 0;
      $checkout_date = $checkin_date;

      if(isset($no_of_days)){
        $date = $checkin_date;
        $date = strtotime($date);
        $date = strtotime("+".$nmofdays." day", $date);
        $checkout_date = date('Y-m-d', $date);
      }
      // if(isset($checkin_date)){
      //   $bookinMonthExeed = $set_admin->allPlaceReservationClosed($checkin_date, $checkout_date);
      // }

      $all_places = Place::where('map_name', $map_name)->get();
      $places = array();
      if(isset($req->t_start)){
        $booking = new Booking;
        foreach ($all_places as $place) {
          if($place->status == -1){
            array_push($places, $place);
            continue;
          }
          // 0 for free, 1 for deactive, and 2 for busy
          $place->status = 0;
          $place->status = $booking->place_is_available($place->place_id, $checkin_date, $checkout_date);
          $place->status = $booking->place_is_available_subs($place->place_id, $checkin_date, $checkout_date, $place->status);
          // if($bookinMonthExeed){
          //   $place->status = $booking->isFreeforFullmonth($place->place_id, $checkin_date, $checkout_date, $place->status);
          // }

          array_push($places, $place);
        }

      }else{
        $places = $all_places;
      }


      $set_admin = SettingAdmin::orderBy('id')->first();

      $booking = new Booking;
      $max_reservation = $booking->max_reservation();


      if($max_reservation >  $set_admin->max_reservation){
        $err_msg2 = "Max Amount of Reservation Reached";
        $maparray = array('map_name' => $map_name, 'map_coods' => $map_coods, 'places'=> $places, 'set_admin'=> $set_admin, 'err_msg2' => $err_msg2);
        return view('users.smallmap')->with('maparray', $maparray);
      }
        
        
      if($set_admin->day == "1"){

        if ($no_of_days < 60) {
          $err_msg2 = "min day limit";
          $maparray = array('map_name' => $map_name, 'map_coods' => $map_coods, 'places'=> $places, 'set_admin'=> $set_admin, 'err_msg2' => $err_msg2);
          return view('users.smallmap')->with('maparray', $maparray);
}

      }

      if($set_admin->day == "2"){

        if ($no_of_days < 30) {
          $err_msg2 = "min day limit";
      $maparray = array('map_name' => $map_name, 'map_coods' => $map_coods, 'places'=> $places, 'set_admin'=> $set_admin, 'err_msg2' => $err_msg2);
      return view('users.smallmap')->with('maparray', $maparray);
}

      }



      if($set_admin->day == "3"){

        if ($no_of_days < 15) {
          $err_msg2 = "min day limit";
      $maparray = array('map_name' => $map_name, 'map_coods' => $map_coods, 'places'=> $places, 'set_admin'=> $set_admin, 'err_msg2' => $err_msg2);
      return view('users.smallmap')->with('maparray', $maparray);

        }
      }


      if(Auth::user()){
        if(number_format($no_of_days)>365 && Auth::user()->role != "admin"){
          $err_msg= "error";
          // return redirect()->route('user.viewsmallplace', $map_name)->with('err_msg', $err_msg);
          $maparray = array('map_name' => $map_name, 'map_coods' => $map_coods, 'places'=> $places, 'set_admin'=> $set_admin, 'err_msg' => $err_msg);
          return view('users.smallmap')->with('maparray', $maparray);
        }
      }else if(number_format($no_of_days)<60){
        $err_msg= "error";
        // return redirect()->route('user.viewsmallplace', $map_name)->with('err_msg', $err_msg);
        $maparray = array('map_name' => $map_name, 'map_coods' => $map_coods, 'places'=> $places, 'set_admin'=> $set_admin, 'err_msg' => $err_msg);
        return view('users.smallmap')->with('maparray', $maparray);
        

      }
      if(isset($req->t_start) && $nmofdays<$set_admin->min_no_days-1){
        $err_msg2 = "min day limit";
        $maparray = array('map_name' => $map_name, 'map_coods' => $map_coods, 'places'=> $places, 'set_admin'=> $set_admin, 'err_msg2' => $err_msg2);
        return view('users.smallmap')->with('maparray', $maparray);
      }elseif(strtotime($set_admin->booking_start)>=strtotime("now") || strtotime($set_admin->booking_end)<=strtotime("now")){
        $err_msg3 = "booking not started";
        $maparray = array('map_name' => $map_name, 'map_coods' => $map_coods, 'places'=> $places, 'set_admin'=> $set_admin, 'err_msg3' => $err_msg3);
        return view('users.smallmap')->with('maparray', $maparray);
      }

      $maparray = array('map_name' => $map_name, 'map_coods' => $map_coods, 'places'=> $places, 'checkin_date' => $checkin_date, 'checkout_date' => $checkout_date, 'set_admin'=> $set_admin);
      return view('users.smallmap')->with('maparray', $maparray);
    }

    public function createbooking($place_id, $checkin, $checkout, $error_msg){

        $booking = new Booking;
        $booking->place_id = $place_id;
        $booking->user_checkin = $checkin;
        $booking->user_checkout = $checkout;
        //make valid URL
        $set_admin = new SettingAdmin;
        if(!$set_admin->bookingURLvalidation($checkin, $checkout)){
          return redirect()->route('error.404');
        }

        if(!$booking->check_availability()){
          return redirect()->route('error.404');
        }

        //Engaged the place for 15 min
        $temp_book = new TempBooking;
        $temp_book->makeEngaged($place_id, $checkin);

        $map_coods = Bigmapmapping::orderBy('id')->get();
        $place = Place::where('place_id',$place_id)->first();
        $set_admin = SettingAdmin::orderBy('id')->first();
        $place->price = $set_admin->adult1_price;
        $maparray = array('map_coods' => $map_coods, 'place' => $place, 'checkin' => $checkin, 'checkout'=> $checkout, 'set_admin' => $set_admin, 'error_msg'=> $error_msg);
        return view('users.bookingplace')->with('maparray', $maparray);
    }

    public function confirmbooking(Request $request){
      // check what is the payment type
      $booking = new Booking;
      $booking->place_id = $request->place_id;
      $booking->user_fullname = $request->user_fullname;
      $booking->payer_name = $request->payer_name;
      $booking->user_surname = $request->user_fullname;
      $booking->user_email = $request->user_email;
      $booking->user_phone = $request->user_phone;
      $booking->user_no_of_guest = ($request->numberofguest)+1;
      $booking->user_no_of_babies = $request->numberofbabies;
      if(isset($request->guestnames[0]))
        $booking->guest_surname1 = $request->guestnames[0];
      if(isset($request->guestnames[1]))
        $booking->guest_surname2 = $request->guestnames[1];
      if(isset($request->guestnames[2]))
        $booking->guest_surname3 = $request->guestnames[2];

      if(isset($request->guestnamesbabies[0]))
        $booking->baby_surname1 = $request->guestnamesbabies[0];
      if(isset($request->guestnamesbabies[1]))
        $booking->baby_surname2 = $request->guestnamesbabies[1];
      if(isset($request->guestnamesbabies[2]))
        $booking->baby_surname3 = $request->guestnamesbabies[2];
      if(isset($request->guestnamesbabies[3]))
        $booking->baby_surname4 = $request->guestnamesbabies[3];


      $booking->user_checkin = $request->t_start;
      $booking->user_checkout = $request->checkout_temp;
      // $booking->user_promo = $request->promocode;
      $datetime1 = new DateTime($booking->user_checkin);
      $datetime2 = new DateTime($booking->user_checkout);
      $interval = $datetime1->diff($datetime2);
      $numberofdays = $interval->format('%a');

      $booking->user_payment_type = $request->payment_type;
      if($booking->user_payment_type == "Credit Card"){
        $booking->user_booking_tracking_id = uniqid('negombo_', true);
        $booking->user_booking_tracking_id = str_replace('.', '_', $booking->user_booking_tracking_id);
      }else if($booking->user_payment_type == "Paypal"){
        $booking->user_booking_tracking_id = uniqid('negombo_', true);
        $booking->user_booking_tracking_id = str_replace('.', '_', $booking->user_booking_tracking_id);
      }
      // promo code handaling
      $place = Place::where('place_id',$booking->place_id)->first();
      $promo = $request->promocode;
      $promoCode = new PromoCode;
      $discount = 0;
      // ToDo calculate place price
      $set_admin = SettingAdmin::orderBy('id')->first();
      $price_temp = $set_admin->calculatePrice($place, $booking->user_checkin, $booking->user_checkout, $booking->user_no_of_guest);
      $place->price =  $price_temp;

      if($promoCode->checkingValidity($promo, $place->map_name, $numberofdays)){
        $booking->user_promo = $promo;


        $discount = $promoCode->discountCalculate($booking->user_promo, $place->price);
        $place->price = $place->price - $discount;
        $booking->paid_ammount = $place->price;
      }else if($booking->user_payment_type == "Agreements"){
        return redirect()->route('user.createbooking', ['place_id' => $booking->place_id, 'checkin' => $booking->user_checkin, 'checkout' => $booking->user_checkout, 'error_msg' => 1]);
      }else if($booking->user_payment_type == "Admin"){
        $place->price = 0;
      }else if(isset($request->promocode)){
        $booking->user_promo = "0";
      }

      if($booking->check_availability()){
        $map_coods = Bigmapmapping::orderBy('id')->get();
        $maparray = array('place'=> $place, 'map_coods' => $map_coods, 'booking'=> $booking, 'discount'=> $discount);
        if($booking->user_payment_type == "Credit Card"){
          $booking->paid_ammount = $place->price;
          $paymentCard = $booking->paywithCard(floatval($booking->paid_ammount));
          $temp_cardPayment = new TempbookingCard;
          $temp_cardPayment->loadAndSavedata($booking, $paymentCard['paymentID']);
          $maparray = array('place'=> $place, 'map_coods' => $map_coods, 'booking'=> $booking, 'discount'=> $discount, 'paymentCardUrl' => $paymentCard['redirect']);
        }
        return view('users.paymentbooking')->with('maparray', $maparray);
      }else{
        return redirect()->route('error.404');
      }
    }

    public function confirmbookingpaymentpaypal($tracking_id){
        $tracking_id = trim($tracking_id);
        $booking = Booking::where('user_booking_tracking_id', $tracking_id)->first();

        if(!isset($booking)){
          return redirect()->route('error.404');
        }
        $place = Place::where('place_id',$booking->place_id)->first();
        $map_coods = Bigmapmapping::orderBy('id')->get();
        $set_admin = SettingAdmin::orderBy('id')->first();
        $maparray = array('place'=> $place, 'map_coods' => $map_coods, 'booking'=> $booking, 'set_admin' => $set_admin);

        return view('users.confirmbooking')->with('maparray', $maparray);
    }




    public function confirmbookingpayment(Request $request){
      $booking = new Booking;
      $booking->place_id = $request->place_id;
      $booking->user_fullname = $request->user_fullname;
      $booking->user_surname = $request->user_fullname;
      $booking->user_email = $request->user_email;
      $booking->user_phone = $request->user_phone;
      $booking->user_no_of_guest = $request->user_no_of_guest;
      $booking->user_no_of_babies = $request->user_no_of_babies;
      $booking->payer_name = $request->payer_name;
      if(isset($request->guest_surname1))
        $booking->guest_surname1 = $request->guest_surname1;
      if(isset($request->guest_surname2))
        $booking->guest_surname2 = $request->guest_surname2;
      if(isset($request->guest_surname3))
        $booking->guest_surname3 = $request->guest_surname3;

      if(isset($request->baby_surname1))
        $booking->baby_surname1 = $request->baby_surname1;
      if(isset($request->baby_surname2))
        $booking->baby_surname2 = $request->baby_surname2;
      if(isset($request->baby_surname3))
        $booking->baby_surname3 = $request->baby_surname3;

      $booking->user_checkin = $request->user_checkin;
      $booking->user_checkout = $request->user_checkout;
      $booking->user_payment_type = $request->user_payment_type;
      $booking->user_booking_tracking_id = uniqid('negombo_', true);

      //getting number of days

      $datetime1 = new DateTime($booking->user_checkin);
      $datetime2 = new DateTime($booking->user_checkout);
      $interval = $datetime1->diff($datetime2);
      $numberofdays = $interval->format('%a');

      $place = Place::where('place_id',$booking->place_id)->first();

      // ToDo calculate place price
      $set_admin = SettingAdmin::orderBy('id')->first();
      $price_temp = $set_admin->calculatePrice($place, $booking->user_checkin, $booking->user_checkout, $booking->user_no_of_guest);
      $place->price =  $price_temp;

      if($request->user_payment_type == "Agreements"){
        $booking->paid_ammount = 0;
        $booking->is_approved = 1;
      }

      if($request->user_payment_type == "Admin"){
        $booking->paid_ammount = 0;
        $place->price =0;
        $booking->is_approved = 1;
      }

      // control promo
      $promo = $request->user_promo;
      $promoCode = new PromoCode;
      $discount = 0;

      if($promoCode->checkingValidity($promo, $place->map_name, $numberofdays) && $promoCode->usedPromoOnce($promo, $numberofdays)){
        $booking->user_promo = $promo;
        $discount = $promoCode->discountCalculate($booking->user_promo, $place->price);
        $place->price = $place->price - $discount;
        $booking->paid_ammount = $place->price;
      }

      if($request->user_payment_type == "Entrance"){
        $booking->paid_ammount = $place->price;
        $booking->is_approved = 1;
      }


      if($booking->check_availability()){
        if($request->user_payment_type =="Agreements")
          $booking->is_approved = 1;
        else
          $booking->is_approved = 1;

        // send mail

        $map_coods = Bigmapmapping::orderBy('id')->get();
        $maparray = array('place'=> $place, 'map_coods' => $map_coods, 'booking'=> $booking, 'set_admin' => $set_admin);


        if(Auth::user()){
          $booking->creator_name = Auth::user()->name;
        }
        $booking->save();
        \Mail::to($booking->user_email)->send(new SendMail($maparray));

        // make free the place after booking is confirmed
        $temp_book = new TempBooking;
        $temp_book->makeFree($booking->place_id, $booking->checkin);

        return view('users.confirmbooking')->with('maparray', $maparray);
      }else{
        return redirect()->route('error.404');
      }
    }

}
