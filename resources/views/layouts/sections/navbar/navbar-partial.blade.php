@php
  use Illuminate\Support\Facades\Auth;
  use Illuminate\Support\Facades\Route;
  $userNotifications = auth()->user()->notifications; // Laravel notifications
@endphp

<!--  Brand demo (display only for navbar-full and hide on below xl) -->
@if (isset($navbarFull))
  <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-4 ms-0">
    <a href="{{ url('/') }}" class="app-brand-link">
      <span class="app-brand-logo demo">@include('_partials.macros')</span>
      <span class="app-brand-text demo menu-text fw-bold">{{ config('variables.templateName') }}</span>
    </a>

    <!-- Display menu close icon only for horizontal-menu with navbar-full -->
    @if (isset($menuHorizontal))
      <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-none">
        <i class="icon-base ti tabler-x icon-sm d-flex align-items-center justify-content-center"></i>
      </a>
    @endif
  </div>
@endif

<!-- ! Not required for layout-without-menu -->
@if (!isset($navbarHideToggle))
  <div
    class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0{{ isset($menuHorizontal) ? ' d-xl-none ' : '' }} {{ isset($contentNavbar) ? ' d-xl-none ' : '' }}">
    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
      <i class="icon-base ti tabler-menu-2 icon-md"></i>
    </a>
  </div>
@endif

<div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
  <!-- Search -->
  <div class="navbar-nav align-items-center">
    <div class="nav-item navbar-search-wrapper px-md-0 px-2 mb-0">
      <a class="nav-item nav-link search-toggler d-flex align-items-center px-0" href="javascript:void(0);">
        <span class="d-inline-block text-body-secondary fw-normal" id="autocomplete"></span>
      </a>
    </div>
  </div>

  <!-- /Search -->
  <ul class="navbar-nav flex-row align-items-center ms-md-auto">

    <!-- Societies Switcher -->
    @role('Society Member')
    <li class="nav-item dropdown-language dropdown">
      <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
         href="javascript:void(0);" data-bs-toggle="dropdown">
        <i class="icon-base ti tabler-transfer icon-22px text-heading"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-end">
        @foreach($memberSocieties as $society)
          <li>
            <form action="{{ route('society.switch') }}" method="POST">
              @csrf
              <input type="hidden" name="society_id" value="{{ $society->id }}">
              <button type="submit"
                      class="dropdown-item {{ session('active_society_id') == $society->id ? 'active' : '' }}">
                {{ $society->name . ' - ' . $society->city  }}
              </button>
            </form>
          </li>
        @endforeach
      </ul>
    </li>
    @endrole

    <!-- Style Switcher -->
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill" id="nav-theme"
        href="javascript:void(0);" data-bs-toggle="dropdown">
        <i class="icon-base ti tabler-sun icon-22px theme-icon-active text-heading"></i>
        <span class="d-none ms-2" id="nav-theme-text">Toggle theme</span>
      </a>
      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme-text">
        <li>
          <button type="button" class="dropdown-item align-items-center active" data-bs-theme-value="light"
            aria-pressed="false">
            <span><i class="icon-base ti tabler-sun icon-22px me-3" data-icon="sun"></i>Light</span>
          </button>
        </li>
        <li>
          <button type="button" class="dropdown-item align-items-center" data-bs-theme-value="dark"
            aria-pressed="true">
            <span><i class="icon-base ti tabler-moon-stars icon-22px me-3" data-icon="moon-stars"></i>Dark</span>
          </button>
        </li>
        <li>
          <button type="button" class="dropdown-item align-items-center" data-bs-theme-value="system"
            aria-pressed="false">
            <span><i class="icon-base ti tabler-device-desktop-analytics icon-22px me-3"
                data-icon="device-desktop-analytics"></i>System</span>
          </button>
        </li>
      </ul>
    </li>
    <!-- / Style Switcher-->

    <!-- Quick links  -->
    <li class="nav-item dropdown-shortcuts navbar-dropdown dropdown">
      <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
        href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="icon-base ti tabler-layout-grid-add icon-22px text-heading"></i>
      </a>
      <div class="dropdown-menu dropdown-menu-end p-0">
        <div class="dropdown-menu-header border-bottom">
          <div class="dropdown-header d-flex align-items-center py-3">
            <h6 class="mb-0 me-auto">Shortcuts</h6>
            <a href="javascript:void(0)"
              class="dropdown-shortcuts-add py-2 btn btn-text-secondary rounded-pill btn-icon" data-bs-toggle="tooltip"
              data-bs-placement="top" title="Add shortcuts"><i
                class="icon-base ti tabler-plus icon-20px text-heading"></i></a>
          </div>
        </div>
        <div class="dropdown-shortcuts-list scrollable-container">
          <div class="row row-bordered overflow-visible g-0">
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ti tabler-calendar icon-26px text-heading"></i>
              </span>
              <a href="{{ url('app/calendar') }}" class="stretched-link">Calendar</a>
              <small>Appointments</small>
            </div>
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ti tabler-file-dollar icon-26px text-heading"></i>
              </span>
              <a href="{{ url('app/invoice/list') }}" class="stretched-link">Invoice App</a>
              <small>Manage Accounts</small>
            </div>
          </div>
          <div class="row row-bordered overflow-visible g-0">
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ti tabler-user icon-26px text-heading"></i>
              </span>
              <a href="{{ url('app/user/list') }}" class="stretched-link">User App</a>
              <small>Manage Users</small>
            </div>
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ti tabler-users icon-26px text-heading"></i>
              </span>
              <a href="{{ url('app/access-roles') }}" class="stretched-link">Role Management</a>
              <small>Permission</small>
            </div>
          </div>
          <div class="row row-bordered overflow-visible g-0">
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ti tabler-device-desktop-analytics icon-26px text-heading"></i>
              </span>
              <a href="{{ url('/') }}" class="stretched-link">Dashboard</a>
              <small>User Dashboard</small>
            </div>
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ti tabler-settings icon-26px text-heading"></i>
              </span>
              <a href="{{ url('pages/account-settings-account') }}" class="stretched-link">Setting</a>
              <small>Account Settings</small>
            </div>
          </div>
          <div class="row row-bordered overflow-visible g-0">
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ti tabler-help-circle icon-26px text-heading"></i>
              </span>
              <a href="{{ url('pages/faq') }}" class="stretched-link">FAQs</a>
              <small>FAQs & Articles</small>
            </div>
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ti tabler-square icon-26px text-heading"></i>
              </span>
              <a href="{{ url('modal-examples') }}" class="stretched-link">Modals</a>
              <small>Useful Popups</small>
            </div>
          </div>
        </div>
      </div>
    </li>
    <!-- Quick links -->

    <!-- Notification -->
    <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-2">
      <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
        href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <span class="position-relative" id="notification_bell">
          <i class="icon-base ti tabler-bell icon-22px text-heading"></i>
          @if ($userNotifications->where('read_at', null)->count() > 0)
            <span class="badge rounded-pill bg-danger badge-dot badge-notifications border"></span>
          @endif
        </span>
      </a>

      <ul class="dropdown-menu dropdown-menu-end p-0">
        <li class="dropdown-menu-header border-bottom">
          <div class="dropdown-header d-flex align-items-center py-3">
            <h6 class="mb-0 me-auto">Notification</h6>
            <div class="d-flex align-items-center h6 mb-0">
              <span class="badge bg-label-primary me-2">{{ $userNotifications->where('read_at', null)->count() }}
                New</span>


              <a href="javascript:void(0)"
                class=" btn btn-icon me-0 top_actions @if ($userNotifications->count() < 1) d-none @endif"
                onclick="markAsDelete(this)" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete All">
                <i class="icon-base ti tabler-trash text-heading"></i>
              </a>

              <a href="javascript:void(0)"
                class="dropdown-notifications-all  btn btn-icon  top_actions @if ($userNotifications->count() < 1) d-none @endif"
                onclick="markAsRead()" data-bs-toggle="tooltip" data-bs-placement="top" title="Mark all as read">
                @if ($userNotifications->where('read_at', null)->count() >= 1)
                  <i class="icon-base ti tabler-mail-filled text-heading mail_icon"></i>
                @else
                  <i class="icon-base ti tabler-mail-opened text-heading mail_icon"></i>
                @endif
              </a>
            </div>
          </div>
        </li>

        <li class="dropdown-notifications-list scrollable-container">
          <ul class="list-group list-group-flush">
            @foreach ($userNotifications as $notification)
              <li
                class="list-group-item list-group-item-action dropdown-notifications-item
                      {{ $notification->read_at ? 'marked_as_read' : '' }}">
                <div class="d-flex">

                  <div class="flex-shrink-0 me-3">
                    <div class="avatar">
                      <img
                        src="{{ isset($notification->data['avator'])
                            ? asset('storage/' . $notification->data['avator'])
                            : asset('assets/img/avatars/1.png') }}"
                        class="rounded-circle" alt="">
                    </div>
                  </div>

                  <div class="flex-grow-1">
                    <h6 class="mb-1 small">
                      {{ $notification->data['title'] ?? 'Notification' }}
                    </h6>
                    <small class="mb-1 d-block text-body">{{ $notification->data['message'] ?? '' }}</small>
                    <small class="text-body-secondary">{{ $notification->created_at->diffForHumans() }}</small>
                  </div>

                  <div class="flex-shrink-0 dropdown-notifications-actions">
                    @if (!$notification->read_at)
                      <a href="javascript:void(0)" class="dropdown-notifications-read "><span
                          onclick="markAsRead(`{{ $notification['id'] }}`)" class="badge badge-dot"
                          title="Mark As Read"></span></a>
                    @endif
                    <a href="javascript:void(0)" class="dropdown-notifications-archive"><span
                        class="icon-base ti tabler-x" onclick="markAsDelete(this,`{{ $notification['id'] }}`)"
                        title="Delete"></span>
                    </a>
                  </div>
                </div>
              </li>
            @endforeach
          </ul>
        </li>

        <li class="border-top">
          <div class="d-grid p-4">
            <a class="btn btn-primary btn-sm d-flex" href="">
              <small class="align-middle">View all notifications</small>
            </a>
          </div>

        </li>
      </ul>
    </li>

    <!--/ Notification -->

    <!-- User -->
    <li class="nav-item navbar-dropdown dropdown-user dropdown">
      <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
        <div class="avatar avatar-online">
          <img
            src="{{ Auth::user()->attachment?->link
                ? asset('storage/' . Auth::user()->attachment->link)
                : asset('assets/img/avatars/1.png') }}"
            class="rounded-circle" />
        </div>
      </a>
      <ul class="dropdown-menu dropdown-menu-end">
        <li>
          <a class="dropdown-item mt-0" href="{{ url('pages/profile-user') }}">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0 me-2">
                <div class="avatar avatar-online">

                  <img
                    src="{{ Auth::user()->attachment?->link
                        ? asset('storage/' . Auth::user()->attachment->link)
                        : asset('assets/img/avatars/1.png') }}"
                    class="rounded-circle" />
                </div>
              </div>
              <div class="flex-grow-1">
                <h6 class="mb-0">
                  @if (Auth::check())
                    {{ ucfirst(Auth::user()->first_name) }} {{ ucfirst(Auth::user()->last_name) }}
                  @else
                    User Name
                  @endif
                </h6>
                <small class="text-body-secondary"> @Auth
                    {{ ucfirst(Auth::user()->roles->pluck('name')->implode(', ')) }}
                  @endauth
                </small>
              </div>
            </div>
          </a>
        </li>
        <li>
          <div class="dropdown-divider my-1 mx-n2"></div>
        </li>
        <li>
          <a class="dropdown-item" href="{{ route('user.edit', ['uuid' => auth()->user()->uuid]) }}">
            <i class="icon-base ti tabler-user me-3 icon-md"></i><span class="align-middle">My Profile</span>
          </a>
        </li>
        <li>
          <a class="dropdown-item" href="{{ url('pages/account-settings-account') }}">
            <i class="icon-base ti tabler-settings me-3 icon-md"></i><span class="align-middle">Settings</span>
          </a>
        </li>
        <li>
          <a class="dropdown-item" href="{{ url('pages/account-settings-billing') }}">
            <span class="d-flex align-items-center align-middle">
              <i class="flex-shrink-0 icon-base ti tabler-file-dollar me-3 icon-md"></i>
              <span class="flex-grow-1 align-middle">Billing</span>
              <span class="flex-shrink-0 badge bg-danger d-flex align-items-center justify-content-center">4</span>
            </span>
          </a>
        </li>
        <li>
          <div class="dropdown-divider my-1 mx-n2"></div>
        </li>
        <li>
          <form action="{{ route('auth-logout') }}" method="POST" id="logout-form">
            @csrf
            <button type="submit" class="dropdown-item">
              <i class="icon-base ti tabler-logout me-3 icon-md"></i>
              <span class="align-middle">Logout</span>
            </button>
          </form>
        </li>

      </ul>
    </li>
    <!--/ User -->
  </ul>
</div>


<!-- toast sms -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="toast_message" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <i class="fa-1x fa-solid fa-bell rounded me-2"></i>

      <strong class="me-auto">Notification</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
      You have new Notification
    </div>
  </div>
</div>


@push('scripts')
  <script>
    const countBadge = document.querySelector('.dropdown-menu-header .badge');
    const dotBadge = document.querySelector('.badge-dot.badge-notifications');
    const listContainer = document.querySelector('.dropdown-notifications-list ul.list-group');
    const icon = document.querySelector('.mail_icon');
    document.addEventListener('DOMContentLoaded', function() {
      if (window.Echo) {
        window.Echo.channel('notification.{{ Auth()->id() }}')
          .listen('PusherNotification', (e) => {
            console.log(e);
            if (e.post_id) {
              const postElement = document.querySelector(
                '.post-item[data-id="' + e.post_id + '"]'
              );
              if (postElement) {
                postElement.remove();
              }
            }
            if (countBadge) {
              let currentCount = parseInt(countBadge.textContent) || 0;
              countBadge.textContent = (currentCount + 1) + ' New';
            }

            if (!document.querySelector('.badge-dot.badge-notifications')) {
              const dot = document.createElement('span');
              dot.className = 'badge rounded-pill bg-danger badge-dot badge-notifications border';
              document.getElementById('notification_bell').appendChild(dot);
            }

            $('.top_actions').removeClass('d-none');
            if (icon) {
              console.log('eeeeeee');
              icon.classList.remove('tabler-mail-opened');
              icon.classList.add('tabler-mail-filled');
            }

            $id = `\`${e.latest_notification.id}\``;
            console.log(e.latest_notification);
            const avatarUrl = e.latest_notification.data?.avator ?
              `/storage/${e.latest_notification.data.avator}` :
              `/assets/img/avatars/1.png`;
            if (listContainer) {
              const li = document.createElement('li');
              li.className = 'list-group-item list-group-item-action dropdown-notifications-item';
              li.innerHTML = `
        <div class="d-flex">
          <div class="flex-shrink-0 me-3">
            <div class="avatar">
              <img  src="${avatarUrl}" class="rounded-circle" alt="">
            </div>
          </div>
          <div class="flex-grow-1">
            <h6 class="mb-1 small">
            ${e.latest_notification.data?.title ?? 'Notification'}
            </h6>
            <small class="mb-1 d-block text-body">${e.latest_notification.data?.message ?? ''}</small>
            <small class="text-body-secondary">Just now</small>
          </div>
          <div class="flex-shrink-0 dropdown-notifications-actions">
            <a href="javascript:void(0)" class="dropdown-notifications-read" onclick="markAsRead(${$id})">
              <span class="badge badge-dot"></span>
            </a>
            <a href="javascript:void(0)" class="dropdown-notifications-archive" onclick="markAsDelete(this, ${$id})">
              <span class="icon-base ti tabler-x"></span>
            </a>
          </div>
        </div>
      `;
              listContainer.prepend(li);
            }
            const toastBody = document.querySelector('#toast_message .toast-body');
            if (toastBody) {
              toastBody.textContent = 'You have a new notification';
            }
            const toastEl = document.getElementById('toast_message');
            if (toastEl) {
              const toast = new bootstrap.Toast(toastEl);
              toast.show();
            }
          });
      } else {
        console.error('Echo not loaded');
      }
    });


    //  for read notification and read all notification
    function markAsRead(id) {

      $.ajaxSetup({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
      });
      $.ajax({
        url: id ? '/notification/read/' + id : '/notification/read/',
        method: 'POST',
        success: function(response) {
          if (!id) {
            if (icon) {
              console.log('eeeeeee');
              icon.classList.remove('tabler-mail-filled');
              icon.classList.add('tabler-mail-opened');
            }
            dotBadge.style.display = 'none';
          }
          if (countBadge && id) {
            let currentCount = parseInt(countBadge.textContent) || 0;
            countBadge.textContent = (currentCount - 1) + ' New';
            console.log(currentCount)
            if (currentCount == 1) {
              dotBadge.style.display = 'none';
            }
          } else {
            countBadge.textContent = '0 New';
          }

          let message = id ? 'You have read notification' : 'You have read all notifications';
          $('.toast-body').text(message);
          const toastEl = document.getElementById('toast_message');
          const toast = new bootstrap.Toast(toastEl);
          toast.show();

        },
        error: function(xhr) {
          console.error('An error occurred:', xhr.responseText);
        }
      });
    };

    // for delete notification and delete all notifications

    function markAsDelete(element, id) {
      let isRead;
      if (id) {
        console.log(id);
        const notificationItem = element.closest('.dropdown-notifications-item');
        isRead = notificationItem.classList.contains('marked_as_read');
      }
      $.ajaxSetup({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
      });

      $.ajax({
        url: id ? '/notification/delete/' + id : '/notification/delete/',
        method: 'POST',
        success: function(response) {
          if (!isRead && id) {
            let currentCount = parseInt(countBadge.textContent) || 0;
            let newCount = currentCount - 1;
            if (newCount <= 0) {
              countBadge.textContent = '0 New';
              dotBadge.style.display = 'none';
            } else {
              countBadge.textContent = newCount + ' New';
            }
          } else if (!id) {
            countBadge.textContent = '0 New';
            if (dotBadge) {
              dotBadge.remove();
            }
            if (listContainer) {
              listContainer.innerHTML = '';
            }
            document.querySelectorAll('.top_actions').forEach(el => {
              el.classList.add('d-none');
            });
          }
          let message = id ? 'Notification deleted' :
            'All notifications deleted';
          $('.toast-body').text(message);
          const toastEl = document.getElementById('toast_message');
          const toast = new bootstrap.Toast(toastEl);
          toast.show();
        },
        error: function(xhr) {
          console.error('Error:', xhr.responseText);
        }
      });
    }
  </script>
@endpush
