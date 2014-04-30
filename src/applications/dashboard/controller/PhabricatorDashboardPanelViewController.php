<?php

final class PhabricatorDashboardPanelViewController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $panel = id(new PhabricatorDashboardPanelQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$panel) {
      return new Aphront404Response();
    }

    $title = $panel->getMonogram().' '.$panel->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Panels'),
      $this->getApplicationURI('panel/'));
    $crumbs->addTextCrumb($panel->getMonogram());

    $header = $this->buildHeaderView($panel);
    $actions = $this->buildActionView($panel);
    $properties = $this->buildPropertyView($panel);
    $timeline = $this->buildTransactions($panel);

    $properties->setActionList($actions);
    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $rendered_panel = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($panel)
      ->renderPanel();

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
        $rendered_panel,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildHeaderView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getRequest()->getUser();

    return id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($panel->getName())
      ->setPolicyObject($panel);
  }

  private function buildActionView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getRequest()->getUser();
    $id = $panel->getID();

    $actions = id(new PhabricatorActionListView())
      ->setObjectURI('/'.$panel->getMonogram())
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $panel,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Panel'))
        ->setIcon('edit')
        ->setHref($this->getApplicationURI("panel/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Standalone'))
        ->setIcon('preview')
        ->setHref($this->getApplicationURI("panel/render/{$id}/")));

    return $actions;
  }

  private function buildPropertyView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($panel);

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $panel);

    $panel_type = $panel->getImplementation();
    if ($panel_type) {
      $type_name = $panel_type->getPanelTypeName();
    } else {
      $type_name = phutil_tag(
        'em',
        array(),
        nonempty($panel->getPanelType(), pht('null')));
    }

    $properties->addProperty(
      pht('Panel Type'),
      $type_name);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    return $properties;
  }

  private function buildTransactions(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getRequest()->getUser();

    $xactions = id(new PhabricatorDashboardPanelTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($panel->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($panel->getPHID())
      ->setTransactions($xactions);

    return $timeline;
  }

}
