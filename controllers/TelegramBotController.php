<?php
/**
 * Telegram Bot Controller
 * @author Ahmed Shan (@thaanu16)
 * 
 */
use Heliumframework\Auth;
use Heliumframework\Notifications;
use Heliumframework\Session;
use Heliumframework\Hash;
use Heliumframework\Controller;
use Heliumframework\Validate;

class TelegramBotController extends Controller
{
    
    public function index()
    {
        $updates = json_decode(file_get_contents("php://input"), true);
        $telegram = new Telegram(_env('TELEGRAM_BOT_TOKEN'), _env('TELEGRAM_BOT_NAME'));
        

        // Handle Call Back Queries
        if ( isset($updates['callback_query']) ) {
            $callBackQueryId = $updates['callback_query']['id'];
            $chatId = $updates['callback_query']['from']['id'];
            $data = $updates['callback_query']['data'];
            $split = explode('_', $data);
            $action = $split[0];
            $id = $split[1];

            if ( $action == 'remindme' ) {

                $reminder = new Reminders();

                $flight = (new Flights())->select_flight_by_id( $id );

                // Check if reminder already set
                if ( $reminder->check($id, $chatId) ) {
                    $telegram->answerCallbackQuery($callBackQueryId, "Reminder already set");
                }
                else {
                    $reminder->set($id, $flight['flight_status'], $chatId);
                    $telegram->answerCallbackQuery($callBackQueryId, "Reminder is set");
                }
                
            }
            else {
                $telegram->sendMessage($chatId, 'Invalid action');
            }

        }

        // Handle Normal Text Message
        if ( isset($updates['message']) ) {
            $chatId = $updates['message']['chat']['id'];
            $text = $updates['message']['text'];

            if ( $text == '/start' ) {
                $telegram->sendMessage($chatId, 'Welcome to Flight MV. You can type a flight number to get updates.');
            }
            else {

                $flightNo = $text;
            
                // Find flight information
                $flights = (new Flights())->find_flight_by_no( $flightNo );

                if ( empty($flights) ) {
                    $telegram->sendMessage($chatId, 'Unable to find flight information for ' . $flightNo);
                }
                else {

                    // Clean up flight information
                    foreach( $flights as $flight ) {
                        $msg   = 'Flight No: ' . $flight['flight_no'] . "\n";
                        $msg  .= 'Airlines: ' . $flight['airline_name'] . "\n";
                        $msg  .= 'Date: ' . date("d F Y", strtotime($flight['scheduled_d'])) . "\n";
                        $msg  .= 'Time: ' . date("H:i", strtotime($flight['scheduled_t'])) . "\n";
                        $msg  .= 'Status: ' . (empty($flight['flight_status']) ? 'NA' : $flight['flight_status']) . "\n";
                        $keyboard = [
                            [
                                ['text' => 'Keep Me Posted', 'callback_data' => 'remindme_' . $flight['ID']]
                            ]
                        ];
                        
                        $telegram->sendInlineKeyboard($chatId, $msg, $keyboard);
                    }
    

                }

            }

        }

    }

}