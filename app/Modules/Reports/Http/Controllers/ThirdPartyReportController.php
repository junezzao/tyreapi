<?php

namespace App\Modules\Reports\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Activity;
use App\Modules\Reports\Repositories\Eloquent\ThirdPartyReportRepository as TpReportRepo;

class ThirdPartyReportController extends Controller
{
	protected $tpReportRepo;

    public function __construct(Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->tpReportRepo = new TpReportRepo;
        $this->authorizer = $authorizer;
    }

    public function process(Request $request) {
    	return response()->json($this->tpReportRepo->process($request->all()));
    }

    public function search(Request $request) {
        return response()->json($this->tpReportRepo->search($request));
    }

    public function show($id)
    {
        $tpReport = $this->tpReportRepo->with('order', 'order_item', 'order.channel.issuing_company', 'media')->findOrFail($id);
        $tprRemarks = $this->tpReportRepo->getRemarks($id);
        $tprLogs = $this->tpReportRepo->getLogs($id);

        $response = array(
            'success'   => true,
            'item'      => $tpReport,
            'remarks'   => $tprRemarks,
            'logs'      => $tprLogs,
        );
        return response()->json($response);
    }

    public function verify($id)
    {
        return response()->json($this->tpReportRepo->verify($id));
    }

    public function bulk_moveTo(Request $request)
    {
        return response()->json($this->tpReportRepo->bulk_moveTo($request->all()));
    }

    public function update(Request $request, $id)
    {
        return response()->json($this->tpReportRepo->update($request->all(), $id));
    }

    public function counters(Request $request) {
        return response()->json($this->tpReportRepo->counters($request->all()));
    }

    public function completeVerifiedOrderItems(Request $status) {
        return response()->json($this->tpReportRepo->completeVerifiedOrderItems($status));
    }

    public function export(Request $request) {
        return response()->json($this->tpReportRepo->export($request));
    } 

    public function exportTaxInvoice(Request $request) {
        return response()->json($this->tpReportRepo->exportTaxInvoice($request));
    }

    public function countVerifiedOrderItems() {
        return response()->json($this->tpReportRepo->countVerifiedOrderItems());
    }

    public function resolveRemark($remarkId) {
        return response()->json($this->tpReportRepo->resolveRemark($remarkId));
    }

    public function addRemark(Request $request) {
        return response()->json($this->tpReportRepo->createRemark($request['id'], $request['userId'], $request['remark'], 'general'));
    }

    public function generateReport(Request $request) {
        $this->tpReportRepo->generateReport($request->all());
        return 'Processing';
    }

    public function destroy($id)
    {
        $response = $this->tpReportRepo->delete($id);
        if ($response == 1) {
            Activity::log('Third Party Report ' . $id . ' has been deleted.', $this->authorizer->getResourceOwnerId());
        }

        return response()->json(['success' => ($response == 1) ? true : false, 'message' => $response]);
    }

    public function discardChecking(Request $request) {
        return response()->json($this->tpReportRepo->discardChecking($request[0]));
    }

}
