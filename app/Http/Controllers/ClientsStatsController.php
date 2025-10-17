<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ClientsStatsController extends Controller
{
    public function index()
    {
        $totalClients = User::count();
        $newClients = User::whereYear('created_at', Carbon::now()->year)->count();
        $regularClients = User::has('orders', '>=', 3)->count();

        // conversion simple sur 2 ans
        $conversion = [];
        for ($year = Carbon::now()->year; $year >= Carbon::now()->year - 1; $year--) {
            $clients = Order::whereYear('created_at', $year)->distinct('user_id')->count('user_id');
            $revenue = Order::whereYear('created_at', $year)->sum('total');
            $conversion[] = [
                'year' => $year,
                'clients' => $clients,
                'percent' => $totalClients > 0 ? round(($clients / $totalClients) * 100, 2) : 0,
                'revenue' => $revenue,
            ];
        }

        // taux de fidélisation simple
        $loyalty = [
            'new' => $totalClients > 0 ? round(($newClients / $totalClients) * 100, 2) : 0,
            'frequent' => $totalClients > 0 ? round(($regularClients / $totalClients) * 100, 2) : 0,
            'inactive' => 0,
            'abandoned' => 0,
        ];

        // segmentation d'âge
        $ageSegments = [];
        if (Schema::hasColumn('users', 'birthdate')) {
            $segments = [
                ['label' => '18 - 25', 'min' => 18, 'max' => 25],
                ['label' => '25 - 45', 'min' => 25, 'max' => 45],
                ['label' => '45 - 60', 'min' => 45, 'max' => 60],
                ['label' => '60 - 90', 'min' => 60, 'max' => 90],
            ];
            foreach ($segments as $s) {
                $minDate = Carbon::now()->subYears($s['max']);
                $maxDate = Carbon::now()->subYears($s['min']);
                $count = User::whereBetween('birthdate', [$minDate, $maxDate])->count();
                $ageSegments[] = [
                    'range' => $s['label'],
                    'count' => $count,
                ];
            }
        }

        // genre
        $gender = [];
        if (Schema::hasColumn('users', 'gender')) {
            $male = User::where('gender', 'male')->count();
            $female = User::where('gender', 'female')->count();
            $other = User::whereNotIn('gender', ['male', 'female'])->count();
            $gender = [
                'male' => $male,
                'female' => $female,
                'other' => $other,
            ];
        }

        return response()->json([
            'total_clients' => $totalClients,
            'new_clients' => $newClients,
            'regular_clients' => $regularClients,
            'conversion_rate' => $conversion,
            'loyalty_rate' => $loyalty,
            'age_segments' => $ageSegments,
            'gender' => $gender,
        ]);
    }
}