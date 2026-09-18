<!DOCTYPE html>
<html>

<head>
  <title>Property Ownership Assigned</title>
</head>

<body>
  <h2><strong>Subject:</strong> Property Ownership Assigned</h2>

  <p>Dear {{ $member->first_name }} {{ $member->last_name ?? '' }},</p>

<p>You have been assigned as the owner of <strong>{{ $propertyName }}</strong>.</p>

  @if($planName)
  <p>Your selected payment plan is: <strong>{{ $planName }}</strong>.</p>
  @endif

  @if($member->email && $password)
  <p>Your login credentials are:</p>
  <p>
    <strong>Email:</strong> {{ $member->email }}<br>
    <strong>Password:</strong> {{ $password }}
  </p>
  @elseif($member->email)
  <p>You can log in using your registered account: <strong>{{ $member->email }}</strong>.</p>
  @endif

  <p>
    You can view your property details and payment plan by logging into the portal.
    If you have any questions, please contact our support team.
  </p>

  <p>Thank you for your cooperation.</p>

  <p>Regards,<br>
    Support Team</p>
</body>

</html>
