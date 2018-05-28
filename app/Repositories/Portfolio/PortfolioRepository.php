<?php namespace App\Repositories\Portfolio;

use Validator;
use App\Portfolio;
use App\CryptoCurrency;
use Carbon\Carbon;
use App\Http\Requests\CoinRequest;
use Illuminate\Support\Facades\Input;
use Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Repositories\Portfolio\PortfolioInterface;
use App\Repositories\CryptoCurrency\CryptoCurrencyRepository;
use App\Charts\PortfolioValueChart;
use DateTime;
use DatePeriod;
use DateInterval;

class PortfolioRepository extends CryptoCurrencyRepository implements PortfolioInterface
{
    protected $model;
    // Constructor to bind model to repo
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function create(CoinRequest $request)
    {
      return Portfolio::create([
        'coin_id' => CryptoCurrency::select('id')->where('name', Input::get('coin'))->first()->id,
        'user_id' => Auth::user()->id,
        'buy_price' => $request->get('price'),
        'amount' => $request->get('amount'),
        'date_purchased' => $request->get('date'),
        'created_at' => Carbon::now()
      ]);
    }

    public function getAll() {
      return Portfolio::all();
    }

    public function getCoinTotalAmount() {
      return Portfolio::groupBy('coin_id')
      ->selectRaw('sum(amount) as totalAmount, coin_id')
      ->orderBy('amount','asc')->get();
    }

    public function getCoinTotalValue($id) {
      return DB::table('portfolio AS p')
        ->selectRaw('sum(p.amount) * c.price_usd AS value')
        ->join('cryptocurrency AS c', 'c.id', '=', 'p.coin_id')
        ->where([
          ['p.user_id', '=', Auth::user()->id],
          ['p.coin_id', '=', $id],
        ])->first();
    }

    public function getCoinDetailWithId($id) {
      return Portfolio::where([
          ['user_id', '=', Auth::user()->id],
          ['coin_id', '=', $id]
        ])->get();
    }

    public function calculateInitialPortfolioValue() {
      return Portfolio::where('user_id', Auth::user()->id)
        ->selectRaw('sum(buy_price * amount) as value')
        ->first()->value;
    }

    public function calculateTotalMarketValue() {
      return Portfolio::where('user_id', Auth::user()->id)
        ->join('cryptocurrency', 'coin_id', '=', 'cryptocurrency.id')
        ->selectRaw('sum(cryptocurrency.price_usd * amount) as value')
        ->first()->value;
    }

    public function createChart()
    {
      $chart = new PortfolioValueChart;
      # Gets current total coin value .
      $data = DB::table('portfolio AS p')
        ->selectRaw('p.date_purchased,sum(p.amount * cp.price) AS dayValue')
        ->join('coin_price AS cp', function($join){
            $join->on('p.date_purchased' , '=', 'cp.date')
                 ->on('p.coin_id' , '=', 'cp.coin_id');
        })
        ->groupBy('p.date_purchased')
        ->orderBy('p.date_purchased', 'asc')
        ->get()
        ->toArray();

      //dd($data);

      $earliest = new DateTime(Portfolio::oldest('date_purchased')->first()->date_purchased);
      $latest = new DateTime(Portfolio::latest('date_purchased')->first()->date_purchased);
      $end = new DateTime("now");
      $di = new DateInterval('P1D');
      $period = new DatePeriod($earliest, $di, $end);
      // Calculates cumulative portfolio value. Adds previous day onto current day.
      $cumulative = array();
      $cumulative[0] = (object)array('date_purchased' => $data[0]->date_purchased, 'total' => (double)$data[0]->dayValue);
      for($i = 1; $i < count($data); $i++) {
        $cumulative[] = (object)array('date_purchased' => $data[$i]->date_purchased, 'total' => $data[$i]->dayValue + $cumulative[$i - 1]->total);
      }
      // Fills empty dates with previous day's portfolio value.
      $graphData = array();
      $index = 0;
      for($i = $earliest; $i <= $latest; $i->modify('+1 day')) {
        if (isset($cumulative[$index]) && $i->format('Y-m-d') == $cumulative[$index]->date_purchased) {
          $graphData[] = $cumulative[$index]->total;
          $index++;
        } else {
          $graphData[] = $cumulative[$index - 1]->total;
        }
        //echo '<pre>'; print_r($labels); echo '</pre>';
      }
      // Ensures data size is same as label size.
      for($i = $latest; $i <= $end; $i->modify('+1 day')) {
        $graphData[] = $graphData[count($graphData) - 1];
      }
      //dd($graphData);
      // Generates the labels using earliest date value to now.
      $labels = array();
      $labelEarliest = new DateTime(Portfolio::oldest('date_purchased')->first()->date_purchased);
      for($i = $labelEarliest; $i <= $end; $i->modify('+1 day')) {
        $labels[] = $i->format('d-M');
      }

      $chart->labels($labels);
      $chart->dataset('Sample', 'line', $graphData)
        ->options([
          'borderColor' => '#c8e2f2',
          'backgroundColor' => '#7cb9e8'
        ]);
      return $chart;

    }


}