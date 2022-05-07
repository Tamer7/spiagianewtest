<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;

use DB;
use App\Models\Bigmapmapping;
use App\Models\SettingAdmin;
use App\Models\Place;
use App\Models\User;
use App\Models\Booking;
use App\Models\TempBooking;
use App\Models\PromoCode;
use App\Models\Trans;
use App\Models\TempbookingCard;

use DateTime;
use Auth;

class AdminPagesController extends Controller
{
  //
  public function __construct()
  {
    $this->middleware('auth');
  }

  public function dashboard(Request $request)
  {
    $set_admin = SettingAdmin::orderBy('id')->first();

    $numberofplace = Place::orderBy('places_id')->count();

    $numberofbookings = Booking::orderBy('id')->count();
    $numberofAggr = Booking::where('user_payment_type', 'Agreements')->count();
    $numberofEntrance = Booking::where('user_payment_type', 'Entrance')->count();

    $earningEntrance = Booking::where('user_payment_type', 'Entrance')->sum('paid_ammount');
    $earningAgr = Booking::where('user_payment_type', 'Agreements')->sum('paid_ammount');
    $earningPaypal = Booking::where('user_payment_type', 'Paypal')->sum('paid_ammount');
    $earningStripe = Booking::where('user_payment_type', 'Stripe')->sum('paid_ammount');
    $earningcard = Booking::where('user_payment_type', 'Credit Card')->sum('paid_ammount');





    $rec_act_bookings = $set_admin->recentActivaty(7);


    function datediffcount($checkin, $checkout){
      $checkin = strtotime($checkin);
      $checkout = strtotime($checkout);
      $datediff = $checkout-$checkin;
        if(round($datediff / (60 * 60 * 24))<0){
          return 0;
        }
      return round($datediff / (60 * 60 * 24))+1;
  }

  $no_of_days = datediffcount($request->t_start,$request->t_end);

    if (isset($request->place_id)) {
      $error_msg = array();
      if ($request->t_start == "null") {
        array_push($error_msg, "Check-in date is not set.");
      }
      $checkin_date = $request->t_start;
      $checkout_date = ($no_of_days) - 1;

      $nmofdays = ($no_of_days) - 1;

      // handle validation for num of day
      if (number_format($no_of_days) <= 0)
        $nmofdays = 0;
      $checkout_date = $checkin_date;
      if (isset($no_of_days)) {
        $date = $checkin_date;
        $date = strtotime($date);
        $date = strtotime("+" . $nmofdays . " day", $date);
        $checkout_date = date('Y-m-d', $date);
      }


      $booking = new Booking;
      $booking->place_id = $request->place_id;
      $booking->user_checkin = $checkin_date;
      $booking->user_checkout = $checkout_date;
      $place = Place::where('place_id', $booking->place_id)->first();
      $isfound = Place::where('place_id', $booking->place_id)->count();
      if ($isfound <= 0)
        array_push($error_msg, "Place id is not found.");
      if ($booking->check_availability() && count($error_msg) == 0) {
        $booking->user_fullname = $request->user_fullname;
        $booking->user_surname = $request->user_fullname;
        $booking->payer_name = Auth::User()->name;
        $booking->user_email = $request->user_email;
        $booking->user_phone = $request->user_phone;
        $booking->user_no_of_guest = ($request->numberofguest) + 1;
        $booking->user_no_of_babies = $request->numberofbabies;
        if (isset($request->guestnames[0]))
          $booking->guest_surname1 = $request->guestnames[0];
        if (isset($request->guestnames[1]))
          $booking->guest_surname2 = $request->guestnames[1];
        if (isset($request->guestnames[2]))
          $booking->guest_surname3 = $request->guestnames[2];

        if (isset($request->guestnamesbabies[0]))
          $booking->baby_surname1 = $request->guestnamesbabies[0];
        if (isset($request->guestnamesbabies[1]))
          $booking->baby_surname2 = $request->guestnamesbabies[1];
        if (isset($request->guestnamesbabies[2]))
          $booking->baby_surname3 = $request->guestnamesbabies[2];
        if (isset($request->guestnamesbabies[3]))
          $booking->baby_surname4 = $request->guestnamesbabies[3];

        $booking->user_booking_tracking_id = uniqid('negombo_', true);
        $booking->user_payment_type = $request->user_payment_type;
        if ($booking->user_payment_type == "Admin") {
          $booking->user_payment_type = 'Admin';
          $booking->paid_ammount = 0;
          $booking->is_approved = 1;
        } else {

          $datetime1 = new DateTime($booking->user_checkin);
          $datetime2 = new DateTime($booking->user_checkout);
          $interval = $datetime1->diff($datetime2);
          $numberofdays = $interval->format('%a');

          $booking->is_approved = 1;
          $booking->user_payment_type = 'Entrance';
          $promo = $request->promocode;
          $promoCode = new PromoCode;
          $discount = 0;
          $price_temp = $set_admin->calculatePrice($place, $booking->user_checkin, $booking->user_checkout, $booking->user_no_of_guest);
          $booking->paid_ammount = $price_temp;
          $place->price = $price_temp;
          if ($promoCode->checkingValidity($promo, $place->map_name, $numberofdays)) {
            $booking->user_promo = $promo;
            $discount = $promoCode->discountCalculate($booking->user_promo, $place->price);
            $place->price = $place->price - $discount;
            $booking->paid_ammount = $place->price;
            $booking->is_approved = 1;
          } else if (isset($request->promocode)) {
            array_push($error_msg, "Given Promo is not worked.");
          }
        }
      } else {
        array_push($error_msg, "Place " . $booking->place_id . " is not available for this time.");
      }

      if (count($error_msg) > 0) {
        $numberofplace = Place::orderBy('places_id')->count();
        // $numberofbookings = Booking::orderBy('id')->count();
        $numberofbookings = 10;
        return view('adminpages.dashboard')->with('numberofplace', $numberofplace)->with('numberofbookings', $numberofbookings)->with('set_admin', $set_admin)->with('error_msg', $error_msg)->with('rec_act_bookings', $rec_act_bookings)->with('numberofAggr', $numberofAggr)
          ->with('numberofEntrance', $numberofEntrance)
          ->with('earningEntrance', $earningEntrance)
          ->with('earningAgr', $earningAgr)
          ->with('earningPaypal', $earningPaypal)
          ->with('earningStripe', $earningStripe)
          ->with('earningcard', $earningcard);
      }

      if (Auth::user()) {
        $booking->creator_name = Auth::user()->name;
      }

      $booking->save();
      return redirect()->route('admin');
    }


    return view('adminpages.dashboard')->with('numberofplace', $numberofplace)->with('numberofbookings', $numberofbookings)->with('set_admin', $set_admin)->with('rec_act_bookings', $rec_act_bookings)->with('numberofAggr', $numberofAggr)->with('numberofEntrance', $numberofEntrance)
      ->with('earningEntrance', $earningEntrance)
      ->with('earningAgr', $earningAgr)
      ->with('earningPaypal', $earningPaypal)
      ->with('earningStripe', $earningStripe)
      ->with('earningcard', $earningcard);
  }


  public function profileview()
  {
    return view('adminpages.profileview');
  }


  public function submitplace_search(Request $request){
      $startDate = date('Y-m-d');
      $endDate = date('Y-m-d');
      $startDate = $request->startDate;
      $newSDate = date("d-m-Y",  strtotime($startDate));
      $request->session()->put('startingRange', $newSDate);

      $endDate = $request->endDate;
      $newEDate = date("d-m-Y",  strtotime($endDate));
      $request->session()->put('endingRange', $newEDate);

      $places = Place::orderBy('place_id')->get();
      $booking = new Booking;
      //$Todaydate = date('Y-m-d');
      foreach ($places as $place) {
        if ($place->status == -1) {
          continue;
        }
        $place->status = $booking->place_is_available($place->place_id, $startDate, $endDate);
        $place->status = $booking->place_is_available_subs($place->place_id, $startDate, $endDate, $place->status);
      }

      return view('adminpages.viewplaces')->with('places', $places)->with('startDate', $startDate)->with('endDate', $endDate);
  }

  public function place_view(Request $request){

    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
    if (isset($request->startDate) && isset($request->$endDate)) {
      $startDate = $request->startDate;
      $endDate = $request->endDate;
    }
    // $request->session()->put('endingRange', $endDate);

    $places = Place::orderBy('place_id')->get();
    $booking = new Booking;
    // $Todaydate = date('Y-m-d');
    foreach ($places as $place) {
      if ($place->status == -1) {
        continue;
      }
      // $place->status =$booking->place_is_available($place->place_id, $Todaydate, $Todaydate);
      // $place->status =$booking->place_is_available_subs($place->place_id, $Todaydate, $Todaydate, $place->status);
      $place->status = $booking->place_is_available($place->place_id, $startDate, $endDate);
      $place->status = $booking->place_is_available_subs($place->place_id, $startDate, $endDate, $place->status);
    }

    return view('adminpages.viewplaces')->with('places', $places)->with('startDate', $startDate)->with('endDate', $endDate);
  }

  public function quickbooking(Request $request)
  {
    $formattedStartDate = $request->qStartDate;
    $formattedEndDate = $request->qEndDate;
    $originalFormatStartDate = date("Y-m-d", strtotime($formattedStartDate));
    $originalFormatEndDate = date("Y-m-d", strtotime($formattedEndDate));

    // Saving the data
    $booking = new Booking();
    $booking->place_id = $request->qID;
    $booking->user_fullname = $request->qFullName;
    $booking->user_checkin = $originalFormatStartDate;
    $booking->user_checkout = $originalFormatEndDate;
    $booking->user_no_of_guest = $request->qNumberOfGuests;
    $booking->user_no_of_babies = $request->qNumberOfBabies;
    $booking->creator_name = $request->qCreatorName;

    //Filling Null for quick booking options
    $booking->payer_name = $request->qCreatorName;
    $booking->user_surname = $request->qFullName;
    $booking->user_email = "Admin";
    $booking->user_phone = "0";
    $booking->is_approved = 1;
    $booking->user_payment_type = "Admin";
    $booking->user_booking_tracking_id = "0";




    $booking->save();

    return redirect()->route('admin.place.viewplaces');
  }


  public function staffsdelete($id)
  {
    if (Auth::user()->role != "admin")
      return redirect()->route('error.404');
    $user = User::find($id);
    $user->delete();
    return redirect()->route('admin.staffs');
  }
  public function staffseditview($id)
  {
    if (!Auth::user())
      return redirect()->route('error.404');
    $user = User::find($id);
    if (is_null($user))
      return redirect()->route('error.404');
    return view('adminpages.editstaff')->with('user', $user);
  }
  public function profileupdate(Request $request)
  {
    $user_id = $request->u_id;
    $user = User::find($user_id);
    $user->name = $request->name;
    $user->email = $request->email;
    $user->role = $request->role;
    $user->save();
    return redirect()->route('admin.staffs');
  }

  public function settingsview()
  {
    if (Auth::user()->role != "admin")
      return redirect()->route('error.404');
    $set_admin = SettingAdmin::orderBy('id')->first();
    return view('adminpages.settings')->with('set_admin', $set_admin);
  }

  public function pricesettingsview()
  {
    if (Auth::user()->role != "admin")
      return redirect()->route('error.404');
    $set_admin = SettingAdmin::orderBy('id')->get();
    return view('adminpages.pricesetting')->with('set_admin', $set_admin);
  }

  public function settingsemailContect()
  {
    if (Auth::user()->role != "admin")
      return redirect()->route('error.404');
    $set_admin = SettingAdmin::orderBy('id')->first();
    return view('adminpages.emailContents')->with('set_admin', $set_admin);
  }

  public function settingsemailContectupdate(Request $request)
  {
    if (Auth::user()->role != "admin")
      return redirect()->route('error.404');
    $set_admin = SettingAdmin::find($request->id)->first();
    $set_admin->booking_email_content = $request->booking_email_content;
    $set_admin->save();
    return redirect()->route('admin.settings.email');
  }

  public function settingsupdateprices(Request $request)
  {
    if (Auth::user()->role != "admin")
      return redirect()->route('error.404');
    $set_admin = SettingAdmin::find($request->id);
    $set_admin->adult1_price = $request->week_adult1_price;
    $set_admin->adult2_price = $request->week_adult2_price;
    $set_admin->adult3_price = $request->week_adult3_price;
    $set_admin->adult4_price = $request->week_adult4_price;
    $set_admin->adult1_price_weekend = $request->weekend_adult1_price;
    $set_admin->adult2_price_weekend = $request->weekend_adult2_price;
    $set_admin->adult3_price_weekend = $request->weekend_adult3_price;
    $set_admin->adult4_price_weekend = $request->weekend_adult4_price;
    $set_admin->day = $request->days;


    $set_admin->save();

    return redirect()->route('admin.settings.price');
  }


  public function settingsupdate(Request $request)
  {
    if (Auth::user()->role != "admin")
      return redirect()->route('error.404');
    $set_admin = SettingAdmin::find($request->id)->first();
    $set_admin->max_no_days = $request->max_no_days;
    $set_admin->max_reservation = $request->max_reservation;
    $set_admin->closing_time = $request->closing_time;
    $set_admin->daily_fee = $request->daily_fee;
    $set_admin->booking_start = $request->starting_time;
    $set_admin->booking_end = $request->ending_time;
    $set_admin->season_start = $request->season_start;
    $set_admin->season_end = $request->season_end;
    $set_admin->day = $request->days;


    $set_admin->save();

    return redirect()->route('admin.settings');
  }


  public function staffsview()
  {
    if (Auth::user()->role != "admin")
      return redirect()->route('error.404');
    $users = User::orderBy('id')->get();
    return view('adminpages.viewstaffs')->with('users', $users);
  }

  public function promocodesviews()
  {
    $promocodes = PromoCode::orderBy('id')->get();
    return view('adminpages.viewpromocodes')->with('promocodes', $promocodes);
  }

  public function promocodedetele($id)
  {
    $promo = PromoCode::find($id);
    if (is_null($promo))
      return redirect()->route('error.404');
    $promo->delete();
    return redirect()->route('admin.promocodes');
  }

  public function promocodeedit($id)
  {
    $promo = PromoCode::find($id);
    if (is_null($promo))
      return redirect()->route('error.404');
    $map_coods = Bigmapmapping::orderBy('id')->get();
    return view('adminpages.editpromo')->with('maps', $map_coods)->with('promo', $promo);
  }

  public function transaction_view()
  {
    $transactions = Trans::orderByDesc('id')->get();
    return view('adminpages.view_transactions')->with('transactions', $transactions);
  }

  public function promocodeupdate(Request $request)
  {
    $promo_temp_code = $request->id;
    $promo = PromoCode::find($promo_temp_code);
    $promo->map_name = $request->map_name;
    if (isset($request->numberofuse))
      $promo->numberofuse = $request->numberofuse;
    else
      $promo->numberofuse = -1;

    $promo->numberofadults = $request->numberofadults;
    $promo->numberofbabies = $request->numberofbabies;
    $promo->discount = $request->discount;
    $promo->save();
    return redirect()->route('admin.promocodes');
  }

  public function promocodechange($id)
  {
    $promo = PromoCode::find($id);
    if (is_null($promo))
      return redirect()->route('error.404');
    if ($promo->status == 0)
      $promo->status = 1;
    else
      $promo->status = 0;
    $promo->save();
    // redial if disabled
    if($promo->numberofuse <= 0){
        return redirect()->route('admin.promocodes.edit', $id);
    }
    return redirect()->route('admin.promocodes');
  }
  public function promocodesviewcreate()
  {
    $map_coods = Bigmapmapping::orderBy('id')->get();
    return view('adminpages.createpromo')->with('maps', $map_coods);
  }

  public function promocodeadd(Request $request){

    $promoCode = new PromoCode();
    $promoCode->map_name = $request->map_name;
    $promoCode->promocode = $request->promocode;
    $promoCode->promo_user = $request->promo_user;
    $promoCode->promo_type = $request->promo_type;
    $promoCode->numberofadults = $request->numberofadults;
    $promoCode->numberofbabies = $request->numberofbabies;
    if (isset($request->numberofuse))
      $promoCode->numberofuse = $request->numberofuse;
    else
      $promoCode->numberofuse = -1;

    $promoCode->discount = $request->discount;
    try {
        $promoCode->save();
        return redirect()->route('admin.promocodes');
      } catch (\Illuminate\Database\QueryException $e) {
        $errors = new MessageBag();
        $errors->add('promocode', 'Promocode already exists.');
        return redirect()->route('admin.promocodes.create')->withErrors($errors);
      }

  }

  public function entrance_view()
  {
    $Bookings = Booking::where('user_payment_type', 'entrance')->get();
    return view('adminpages.entranceview')->with('Bookings', $Bookings);
  }

  public function viewbookingdelaits($id)
  {
    $Booking = Booking::where('id', $id)
      ->first();
    return view('adminpages.viewbookingdelaits')->with('Booking', $Booking);
  }
  public function editbookingdelaits($id)
  {
    $Booking = Booking::where('id', $id)->first();
    $days = $Booking->datediffcount($Booking->user_checkin, $Booking->user_checkout);
    $Booking->user_days = $days;
    return view('adminpages.editbookingdelaits')->with('Booking', $Booking);
  }
  public function updatebookingdelaits(Request $request){
    $booking_id = $request->booking_id;
      $Booking = Booking::where('id', $booking_id)->first();
      $user_name = trim($request->user_fullname);
      $Booking->place_id= $request->place_id;
      $Booking->user_fullname = $user_name;
      $Booking->user_email = $request->user_email;
      $Booking->user_phone = $request->user_phone;
      $Booking->user_no_of_guest = $request->numberofguest + 1;
      $Booking->user_no_of_babies = $request->numberofbabies;
      if(isset($request->guestnames1))
        $Booking->guest_surname1 = $request->guestnames1;
      else{
        $Booking->guest_surname1=NULL;
      }
      if(isset($request->guestnames2))
        $Booking->guest_surname2 = $request->guestnames2;
      else{
          $Booking->guest_surname2=NULL;
      }
      if(isset($request->guestnames3))
        $Booking->guest_surname3 = $request->guestnames3;
      else{
          $Booking->guest_surname3=NULL;
      }
      if(isset($request->guestnamesbabies1))
        $Booking->baby_surname1 = $request->guestnamesbabies1;
      else{
        $Booking->baby_surname1=NULL;
      }
      if(isset($request->guestnamesbabies2))
        $Booking->baby_surname2 = $request->guestnamesbabies2;
      else{
          $Booking->baby_surname2=NULL;
      }
      if(isset($request->guestnamesbabies3))
        $Booking->baby_surname3 = $request->guestnamesbabies3;
      else{
          $Booking->baby_surname3=NULL;
      }
      if(isset($request->guestnamesbabies4))
        $Booking->baby_surname4 = $request->guestnamesbabies4;
      else{
          $Booking->baby_surname4=NULL;
      }
      $Booking->user_checkin = $request->t_start;
      $user_checkout = $request->booking_day_end-1;
      $user_checkout = date('Y-m-d', strtotime("+".$user_checkout." day", strtotime($request->t_start)));
      $Booking->user_checkout =  $user_checkout;
      if($Booking->check_availability()){
        // $settingAdmin = new SettingAdmin();
        // $Booking->paid_ammount = $settingAdmin->calculatePriceNew($Booking->place_id,$Booking->user_checkin,$user_checkout,$Booking->user_no_of_guest);
        $Booking->save();
        return redirect()->route('admin.booking.viewbookings');
      }else{
        return back()->withErrors(['Place is not available for this time. Please choose another dates!']);
      }
  }

  public function booking_view()
  {

    $lastdateofFive = date("Y-m-d", strtotime("-2 day"));

    $Bookings = DB::table('bookings')
      ->select(
        'bookings.id AS ID',
        'bookings.place_id',
        'bookings.user_fullname',
        'bookings.user_email',
        'bookings.user_phone',
        'promo_codes.promo_type',
        'bookings.user_no_of_guest',
        'bookings.user_no_of_babies',
        'bookings.user_checkin',
        'bookings.user_checkout',
        'bookings.user_payment_type',
        'bookings.created_at'

      )
      ->where('user_checkout', '>', $lastdateofFive)
      ->leftJoin('promo_codes', 'bookings.user_promo', '=', 'promo_codes.promocode')
      ->orderByDesc('bookings.id')
      ->get();
    return view('adminpages.viewbookings')
      ->with('Bookings', $Bookings);
  }
  public function booking_view_all()
  {

    $Bookings = DB::table('bookings')
      ->select(
        'bookings.id AS ID',
        'bookings.place_id',
        'bookings.user_fullname',
        'bookings.user_email',
        'bookings.user_phone',
        'promo_codes.promo_type',
        'bookings.user_no_of_guest',
        'bookings.user_no_of_babies',
        'bookings.user_checkin',
        'bookings.user_checkout',
        'bookings.user_payment_type',
        'bookings.created_at'
      )
      ->leftJoin('promo_codes', 'bookings.user_promo', '=', 'promo_codes.promocode')
      ->get();
    return view('adminpages.viewallbookings')->with('Bookings', $Bookings);
  }
  public function deletebookingdelaits($id)
  {
    $Booking = Booking::where('id', $id)->first();
    $Booking->delete();
    return redirect()->route('admin.booking.viewbookings');
  }
  public function subscription_view()
  {
    $Bookings = Booking::where('user_payment_type', 'Agreements')->get();
    return view('adminpages.subscription_view')->with('Bookings', $Bookings);
  }

  public function subscription_approve($id)
  {
    $Booking = Booking::where('id', $id)->first();
    $Booking->is_approved = 1;
    $Booking->save();
    $Bookings = Booking::where('user_payment_type', 'Agreements')->get();
    return view('adminpages.subscription_view')->with('Bookings', $Bookings);
  }
  public function subscription_reject($id)
  {
    $Booking = Booking::where('id', $id)->first();
    $Booking->is_approved = 0;
    $Booking->save();
    $Bookings = Booking::where('user_payment_type', 'Agreements')->get();
    return view('adminpages.subscription_view')->with('Bookings', $Bookings);
  }

  public function place_create()
  {
    $map_coods = Bigmapmapping::orderBy('id')->get();
    return view('adminpages.createplace')->with('map_coods', $map_coods);
  }

  public function place_edit($place_id)
  {
    $place = Place::where('place_id', $place_id)->first();
    $map_coods = Bigmapmapping::orderBy('id')->get();

    if ($place) {
      return view('adminpages.editplace')->with('place', $place)->with('map_coods', $map_coods);
    } else {
      //handle error id
      return redirect()->route('error.404');
    }
  }

  public function place_delete($place_id)
  {
    $place = Place::where('place_id', $place_id)->first();
    //handle error id
    if ($place)
      $place->delete();
    else
      return redirect()->route('error.404');

    return redirect()->route('admin.place.viewplaces');
  }

  public function place_store(Request $request)
  {

    $place = new Place;
    $place->place_id = $request->place_id;
    $place->place_name = $request->place_name;
    $place->map_name = $request->map_name;
    $place->co_xl = $request->co_xl;
    $place->co_yl = $request->co_yl;
    $place->co_xs = $request->co_xs;
    $place->co_ys = $request->co_ys;
    $place->place_description = "none";
    $place->price1 = $request->place_price1;
    $place->price2 = $request->place_price2;
    $place->price3 = $request->place_price3;
    $place->price4 = $request->place_price4;
    $place->currency_type = $request->currency_type;
    try {
      $place->save();
    } catch (\Illuminate\Database\QueryException $e) {
      // add your error messages:
      $errors = new MessageBag();
      $errors->add('place_id', 'Place id already exists.');
      return redirect()->route('admin.place.create')->withErrors($errors);
    }

    return redirect()->route('admin.place.create');
    // return redirect()->route('admin.place.viewplaces');
  }


  public function place_update(Request $request, $place_id)
  {

    $place = Place::where('place_id', $place_id)->first();
    $place->place_id = $request->place_id;
    $place->place_name = $request->place_name;
    $place->map_name = $request->map_name;
    $place->co_xl = $request->co_xl;
    $place->co_yl = $request->co_yl;
    $place->co_xs = $request->co_xs;
    $place->co_ys = $request->co_ys;
    $place->place_description = $request->place_description;
    $place->price1 = $request->place_price1;
    $place->price2 = $request->place_price2;
    $place->price3 = $request->place_price3;
    $place->price4 = $request->place_price4;
    try {
      $place->save();
    } catch (\Illuminate\Database\QueryException $e) {
      // add your error messages:
      $errors = new MessageBag();
      $errors->add('place_id', 'Place id already exists.');
      return redirect()->route('admin.place.edit')->withErrors($errors);
    }

    return redirect()->route('admin.place.viewplaces');
  }


  public function changeStatus($place_id)
  {
    $place = Place::where('place_id', $place_id)->first();
    if ($place->status == -1) {
      $place->status = 0;
    } else {
      $place->status = -1;
    }
    $place->save();
    return redirect()->route('admin.place.viewplaces');
  }
}
