<!DOCTYPE html>
<html>

<head>
  <title>Society Blocked</title>
</head>

<body>
<h2><strong> Subject: </strong>  Society Blocked Notification,</h2>

<strong> Dear {{ $society->name }} Team, </strong> <br>
    This is a formal notification to inform you that your society, {{ $society->name }} (Address: {{ $society->address }}) (City: {{ $society->city }}), has
    been blocked. This action was taken due to a violation of our terms of service or a security policy.
    As a result, access to the account is currently suspended. To initiate a review and resolve this matter, please
    contact our support team.<br>
    Thank you for your cooperation.
    Sincerely,
  </p>
  <p>If you believe this is a mistake, please contact support.</p>

  <br>
  <p>Regards,<br>
    Support Team</p>
</body>

</html>
