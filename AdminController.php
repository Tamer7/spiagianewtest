<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Exports\UsersExport;
use App\Exports\BookingExportByDate;
use App\Exports\BookExportPayCat;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    //
    public function __construct(){
        $this->middleware('auth');
    }
    public function export(){
      if(Auth::user()->role != "admin")
        return redirect()->route('error.404');
        return Excel::download(new UsersExport, 'bookings.xlsx');
    }


    public function exportdate($date){
      if(Auth::user()->role != "admin")
        return redirect()->route('error.404');
        return Excel::download(new BookingExportByDate($date), 'bookings('.$date.').xlsx');
    }


    public function exportcategory($category){
      if(Auth::user()->role != "admin")
        return redirect()->route('error.404');
        return Excel::download(new BookExportPayCat($category), 'bookings('.$category.').xlsx');
    }
}
