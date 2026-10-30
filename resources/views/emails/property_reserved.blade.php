<!DOCTYPE html>
<html>

<head>
  <title>Property Ownership Reservation</title>
</head>

<body>
  <h2><strong>Subject:</strong> Property Ownership Reservation</h2>

  <p>Dear {{ $member->first_name }} {{ $member->last_name ?? '' }},</p>

<p>Congratulations! <strong>{{ $propertyName }}</strong> has been reserved for you as a prospective owner.</p>

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
